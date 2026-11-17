import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../providers/auth_provider.dart';
import '../data/operations_api.dart';
import 'field_reports_screen.dart';
import 'workspace_ui.dart';

/// Each signed inventory snapshot has its own server-issued idempotency key.
class CountOutbox {
  static final _active = <String>{};
  static String prefix(OperationsApi api, String user) =>
      'counts:${api.uri('')}:$user:';
  static Future<void> flush(OperationsApi api, String user) async {
    final keyPrefix = prefix(api, user);
    if (!_active.add(keyPrefix)) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      for (final key
          in prefs
              .getKeys()
              .where((key) => key.startsWith(keyPrefix))
              .toList()) {
        final data = jsonDecode(prefs.getString(key)!) as Map<String, dynamic>;
        final photo = data.remove('photo') as String?;
        await api.upload(
          'operations/stock-checks',
          data,
          files: {
            if (photo != null)
              'photo': [OperationsFile('stock.jpg', base64Decode(photo))],
          },
        );
        await prefs.remove(key);
      }
    } finally {
      _active.remove(keyPrefix);
    }
  }
}

class OutletWork extends StatefulWidget {
  final bool office;
  const OutletWork({super.key, this.office = false});
  @override
  State<OutletWork> createState() => _OutletWorkState();
}

class _OutletWorkState extends State<OutletWork> {
  late final OperationsApi api;
  late final String user;
  late final String cache;
  List<Map<String, dynamic>> outlets = [], items = [], recent = [];
  final counts = <int, int>{};
  final remarks = <int, String>{};
  String? snapshot, notice, photo;
  Map<String, dynamic> evidence = {};
  int? outlet;
  String tier = 'farmer', payment = 'cash';
  bool busy = false;
  @override
  void initState() {
    super.initState();
    api = operationsApi(context);
    user = context.read<AuthProvider>().user!.id;
    cache = 'inventory:${api.uri('')}:$user';
    load();
  }

  Future<void> load({int? id}) async {
    setState(() => busy = true);
    final prefs = await SharedPreferences.getInstance();
    final path = widget.office
        ? 'office-stock'
        : id == null
        ? 'stock-checks/outlets'
        : 'stock-checks/outlets/$id';
    final key = '$cache:$path';
    try {
      Map<String, dynamic> data;
      try {
        data = await api.get('operations/$path');
        if (!widget.office) await prefs.setString(key, jsonEncode(data));
        notice = null;
      } catch (e) {
        final saved = prefs.getString(key);
        if (widget.office || saved == null) rethrow;
        data = jsonDecode(saved);
        notice = 'Using the last saved inventory snapshot.';
      }
      if (!mounted) return;
      if (id == null && !widget.office) {
        outlets = OperationsApi.rows(data);
        recent = List<Map<String, dynamic>>.from(data['recent'] ?? []);
      } else {
        items = OperationsApi.rows(data);
        snapshot = data['snapshot'];
        counts.clear();
        remarks.clear();
        photo = null;
        evidence = {};
      }
    } catch (e) {
      notice = operationError(e);
      items = [];
      snapshot = null;
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> capture() async {
    try {
      final file = await ImagePicker().pickImage(
        source: ImageSource.camera,
        maxWidth: 1100,
        imageQuality: 65,
      );
      if (file == null) return;
      final fix = await operationsPlatform.invokeMapMethod<String, dynamic>(
        'location',
      );
      if (fix == null)
        throw StateError('Location required for the photograph.');
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      setState(() {
        photo = base64Encode(bytes);
        evidence = {
          'latitude': fix['latitude'],
          'longitude': fix['longitude'],
          'captured_at': DateTime.fromMillisecondsSinceEpoch(
            (fix['timestamp'] as num).toInt(),
            isUtc: true,
          ).toIso8601String(),
        };
      });
    } catch (e) {
      if (mounted) setState(() => notice = operationError(e));
    }
  }

  Future<void> submit() async {
    setState(() => busy = true);
    try {
      if (widget.office) {
        final lines = [
          for (final row in items)
            if ((counts[row['product_id']] ?? 0) > 0)
              {
                'product_id': row['product_id'],
                'quantity': counts[row['product_id']],
              },
        ];
        if (lines.isEmpty) throw StateError('Enter a sale quantity.');
        await api.post('operations/office-sales', {
          'items': lines,
          'payment_method': payment,
          'price_tier': tier,
        });
        if (!mounted) return;
        await load();
        notice = 'Direct sale recorded.';
      } else {
        if (snapshot == null || items.isEmpty)
          throw StateError('Select an outlet with inventory.');
        final lines = <Map<String, dynamic>>[];
        for (final row in items) {
          final id = row['product_id'] as int;
          final count = counts[id];
          if (count == null || count < 0)
            throw StateError('Count every SKU in whole pieces.');
          if (count != double.parse(row['quantity'].toString()) &&
              (remarks[id] ?? '').trim().isEmpty)
            throw StateError('Explain each difference.');
          lines.add({'product_id': id, 'count': count, 'remark': remarks[id]});
        }
        final prefs = await SharedPreferences.getInstance();
        final timestamp = DateTime.now().toUtc().toIso8601String();
        final saved = await prefs.setString(
          CountOutbox.prefix(api, user) + timestamp,
          jsonEncode({
            'snapshot': snapshot,
            'lines': lines,
            'counted_at': timestamp,
            if (photo != null) 'photo': photo,
            ...evidence,
          }),
        );
        if (!saved) throw StateError('Could not save count on this device.');
        await prefs.remove('$cache:stock-checks/outlets/$outlet');
        snapshot = null;
        items = [];
        outlet = null;
        photo = null;
        evidence = {};
        try {
          await CountOutbox.flush(api, user);
          notice = 'Stock count submitted.';
        } catch (e) {
          notice = 'Count saved on this device for retry. ${operationError(e)}';
        }
      }
    } catch (e) {
      notice = operationError(e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  void dispose() {
    api.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: widget.office ? 'Office sale' : 'Weekly stock count',
    child: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (notice != null) Text(notice!, semanticsLabel: notice),
        if (busy) const LinearProgressIndicator(),
        if (!widget.office)
          DropdownButtonFormField<int>(
            key: ValueKey(outlet),
            initialValue: outlet,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Franchise'),
            items: [
              for (final row in outlets)
                DropdownMenuItem(
                  value: row['id'] as int,
                  child: Text(row['name']),
                ),
            ],
            onChanged: busy
                ? null
                : (id) {
                    outlet = id;
                    load(id: id);
                  },
          ),
        if (widget.office) ...[
          DropdownButtonFormField(
            initialValue: tier,
            decoration: const InputDecoration(labelText: 'Price list'),
            items: [
              for (final x in ['farmer', 'retailer'])
                DropdownMenuItem(value: x, child: Text(x)),
            ],
            onChanged: (v) => setState(() => tier = v!),
          ),
          DropdownButtonFormField(
            initialValue: payment,
            decoration: const InputDecoration(labelText: 'Payment method'),
            items: [
              for (final x in ['cash', 'mobile_money', 'bank_transfer'])
                DropdownMenuItem(value: x, child: Text(x)),
            ],
            onChanged: (v) => setState(() => payment = v!),
          ),
        ],
        for (final row in items)
          Card(
            key: ValueKey('$outlet-${row['product_id']}'),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${row['name']} · ${row['sku']}'),
                  Text('System stock: ${row['quantity']} pieces'),
                  TextFormField(
                    decoration: InputDecoration(
                      labelText: widget.office
                          ? 'Sell pieces'
                          : 'Physical count',
                    ),
                    keyboardType: TextInputType.number,
                    onChanged: (s) => setState(() {
                      final n = int.tryParse(s);
                      if (n == null) {
                        counts.remove(row['product_id']);
                      } else {
                        counts[row['product_id']] = n;
                      }
                    }),
                  ),
                  if (!widget.office &&
                      counts.containsKey(row['product_id'])) ...[
                    Text(
                      'Difference: ${counts[row['product_id']]! - double.parse(row['quantity'].toString())}',
                    ),
                    if (counts[row['product_id']] !=
                        double.parse(row['quantity'].toString()))
                      TextFormField(
                        decoration: const InputDecoration(
                          labelText: 'Reason for difference',
                        ),
                        onChanged: (s) => remarks[row['product_id']] = s,
                      ),
                  ],
                ],
              ),
            ),
          ),
        if (!widget.office && items.isNotEmpty)
          TextButton.icon(
            onPressed: busy ? null : capture,
            icon: const Icon(Icons.camera_alt_outlined),
            label: Text(
              photo == null
                  ? 'Add stock photograph'
                  : 'Retake stock photograph',
            ),
          ),
        if (items.isNotEmpty)
          FilledButton(
            onPressed: busy ? null : submit,
            child: Text(widget.office ? 'Record sale' : 'Submit count'),
          ),
        if (!widget.office && outlet == null && recent.isNotEmpty)
          ExpansionTile(
            title: const Text('Recent stock checks'),
            children: [
              for (final check in recent)
                ListTile(
                  title: Text(check['name']),
                  subtitle: Text(check['counted_at']),
                ),
            ],
          ),
        if (!busy && items.isEmpty)
          TextButton(
            onPressed: () => load(id: outlet),
            child: const Text('Refresh inventory'),
          ),
      ],
    ),
  );
}
