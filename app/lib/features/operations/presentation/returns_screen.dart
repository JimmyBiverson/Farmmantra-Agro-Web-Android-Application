import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/enums/user_role.dart';
import '../../../core/utils/formatters.dart';
import '../data/operations_api.dart';
import 'workspace_ui.dart';

class ReturnsScreen extends StatefulWidget {
  const ReturnsScreen({super.key});
  @override
  State<ReturnsScreen> createState() => _ReturnsScreenState();
}

class _ReturnsScreenState extends State<ReturnsScreen> {
  final key = GlobalKey<RemoteRecordsState>();
  final Set<String> busy = {};
  @override
  Widget build(BuildContext context) {
    final role = context.watch<AuthProvider>().userRole;
    return WorkspacePage(
      title: 'Goods returns',
      actions: [
        if (role == UserRole.franchisePartner)
          IconButton(
            tooltip: 'Request goods return',
            icon: const Icon(Icons.add_circle_outline),
            onPressed: () async {
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ReturnFormScreen()),
              );
              key.currentState?.reload();
            },
          ),
      ],
      child: RemoteRecords(
        key: key,
        path: 'operations/returns',
        emptyTitle: 'No goods returns',
        emptyMessage:
            'Returns follow Sales Head review, Admin review, then physical receipt at the warehouse.',
        builder: (context, r, reload) {
          final stage = r['status'];
          final canAct =
              (stage == 'sales_head' && role == UserRole.salesHead) ||
              (stage == 'admin' && role == UserRole.systemAdministrator) ||
              (stage == 'warehouse' && role == UserRole.warehouse);
          return Card(
            margin: const EdgeInsets.only(bottom: 16),
            elevation: 0,
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  StatusPill(stage),
                  const SizedBox(height: 12),
                  Text(
                    r['number'],
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  Text(
                    'Order ${r['order']?['order_number'] ?? r['order_id']} · ${r['franchise']?['name'] ?? ''}',
                  ),
                  const Divider(height: 28),
                  Text(r['reason']),
                  Text('Transport: ${r['transport']}'),
                  const SizedBox(height: 12),
                  for (final item in r['items'] ?? [])
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Text(
                        '${item['product_name']} · ${item['quantity']} × ${Formatters.currency(item['unit_price'])}\nBatch: ${item['batch_number'] ?? 'Not recorded'} · Expiry: ${item['expires_at'] ?? 'Not recorded'}',
                      ),
                    ),
                  if ((r['photos'] as List?)?.isNotEmpty ?? false)
                    Wrap(
                      spacing: 8,
                      children: [
                        for (var i = 0; i < (r['photos'] as List).length; i++)
                          _ReturnPhoto(returnId: r['id'].toString(), index: i),
                      ],
                    ),
                  for (final h in r['history'] ?? [])
                    Padding(
                      padding: const EdgeInsets.only(top: 10),
                      child: Text(
                        '${h['stage']} · ${h['name']}\n${h['notes']}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xff637269),
                        ),
                      ),
                    ),
                  if (canAct)
                    Padding(
                      padding: const EdgeInsets.only(top: 16),
                      child: Wrap(
                        spacing: 10,
                        children: [
                          OutlinedButton(
                            onPressed: busy.contains(r['id'].toString())
                                ? null
                                : () => _review(r, false, reload),
                            child: const Text('Decline'),
                          ),
                          FilledButton(
                            onPressed: busy.contains(r['id'].toString())
                                ? null
                                : () => _review(r, true, reload),
                            child: Text(
                              stage == 'warehouse'
                                  ? 'Confirm physically received'
                                  : 'Approve return',
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _review(
    Map<String, dynamic> r,
    bool approve,
    VoidCallback reload,
  ) async {
    final data = await reviewNotes(
      context,
      title: r['status'] == 'warehouse'
          ? 'Confirm physical receipt'
          : 'Review goods return',
    );
    if (data == null || !mounted) return;
    final api = operationsApi(context);
    setState(() => busy.add(r['id'].toString()));
    try {
      await api.post('operations/returns/${r['id']}/review', {
        'approve': approve,
        'notes': data['notes'],
      });
      reload();
    } catch (e) {
      if (mounted) showOperationError(context, e);
    } finally {
      api.dispose();
      if (mounted) setState(() => busy.remove(r['id'].toString()));
    }
  }
}

class _ReturnPhoto extends StatelessWidget {
  final String returnId;
  final int index;
  const _ReturnPhoto({required this.returnId, required this.index});
  @override
  Widget build(BuildContext context) {
    final api = operationsApi(context);
    final url = api
        .uri('operations/returns/$returnId/photos/$index')
        .toString();
    final headers = api.headers;
    api.dispose();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Image.network(
          url,
          headers: headers,
          width: 100,
          height: 100,
          fit: BoxFit.cover,
          errorBuilder: (_, error, stack) => const SizedBox(
            width: 100,
            height: 100,
            child: Icon(Icons.broken_image_outlined),
          ),
        ),
      ),
    );
  }
}

class ReturnFormScreen extends StatefulWidget {
  const ReturnFormScreen({super.key});
  @override
  State<ReturnFormScreen> createState() => _ReturnFormScreenState();
}

class _ReturnFormScreenState extends State<ReturnFormScreen> {
  final key = GlobalKey<FormState>();
  final reason = TextEditingController(), transport = TextEditingController();
  final Map<String, TextEditingController> quantities = {};
  List<Map<String, dynamic>> orders = [];
  Map<String, dynamic>? order;
  List<OperationsFile> photos = [];
  bool loading = true, saving = false;
  String? error;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = operationsApi(context);
    try {
      final r = await api.get('operations/returns/eligible-orders');
      if (mounted) setState(() => orders = OperationsApi.rows(r));
    } catch (e) {
      if (mounted) setState(() => error = operationError(e));
    } finally {
      api.dispose();
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void dispose() {
    reason.dispose();
    transport.dispose();
    for (final c in quantities.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _select(String? id) {
    for (final c in quantities.values) {
      c.dispose();
    }
    quantities.clear();
    setState(() {
      order = orders.firstWhere((r) => r['id'].toString() == id);
      for (final item in order!['items']) {
        quantities[item['product_id'].toString()] = TextEditingController();
      }
    });
  }

  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Request a return',
    child: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
        ? Center(child: Text(error!))
        : orders.isEmpty
        ? const WorkspaceEmpty(
            title: 'No received orders yet',
            message: 'Accept a delivery before requesting a return against it.',
          )
        : Form(
            key: key,
            child: ListView(
              padding: const EdgeInsets.all(22),
              children: [
                Text(
                  'Return the right items, with a clear record.',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 12),
                const Text(
                  'Select a received order, enter quantities, attach photographs and describe transport. Stock and credit are updated after warehouse receipt.',
                ),
                const SizedBox(height: 22),
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Received order',
                  ),
                  items: orders
                      .map(
                        (r) => DropdownMenuItem(
                          value: r['id'].toString(),
                          child: Text(r['order_number']),
                        ),
                      )
                      .toList(),
                  onChanged: _select,
                  validator: (v) =>
                      v == null ? 'Choose a received order' : null,
                ),
                if (order != null) ...[
                  const SizedBox(height: 16),
                  for (final item in order!['items'])
                    Card(
                      elevation: 0,
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item['product']?['name'] ?? 'Product',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(
                              '${Formatters.currency(item['unit_price'])} · Expiry ${item['product']?['expires_at'] ?? 'Not recorded'}',
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller:
                                  quantities[item['product_id'].toString()],
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                    decimal: true,
                                  ),
                              decoration: const InputDecoration(
                                labelText:
                                    'Quantity to return (leave blank to skip)',
                              ),
                              validator: (v) {
                                if (v == null || v.isEmpty) return null;
                                final q = double.tryParse(v);
                                return q == null || q <= 0
                                    ? 'Enter a positive quantity'
                                    : null;
                              },
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
                const SizedBox(height: 16),
                TextFormField(
                  controller: reason,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Reason for return',
                  ),
                  validator: (v) =>
                      (v?.trim().isEmpty ?? true) ? 'Enter the reason' : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: transport,
                  decoration: const InputDecoration(
                    labelText: 'Transport mode and details',
                  ),
                  validator: (v) => (v?.trim().isEmpty ?? true)
                      ? 'Enter transport details'
                      : null,
                ),
                const SizedBox(height: 16),
                OutlinedButton.icon(
                  onPressed: saving
                      ? null
                      : () async {
                          final selected = await ImagePicker().pickMultiImage(
                            maxWidth: 1800,
                            imageQuality: 80,
                            limit: 5,
                          );
                          final files = <OperationsFile>[];
                          for (final f in selected.take(5)) {
                            files.add(
                              OperationsFile(f.name, await f.readAsBytes()),
                            );
                          }
                          if (mounted && files.isNotEmpty) {
                            setState(() => photos = files);
                          }
                        },
                  icon: const Icon(Icons.add_photo_alternate_outlined),
                  label: Text(
                    photos.isEmpty
                        ? 'Attach return photographs'
                        : '${photos.length} photographs selected',
                  ),
                ),
                if (photos.isNotEmpty)
                  Wrap(
                    spacing: 8,
                    children: photos
                        .map(
                          (f) => Image.memory(
                            Uint8List.fromList(f.bytes),
                            width: 70,
                            height: 70,
                            fit: BoxFit.cover,
                          ),
                        )
                        .toList(),
                  ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: saving ? null : _save,
                  child: Text(saving ? 'Submitting…' : 'Submit return request'),
                ),
                const SizedBox(height: 30),
              ],
            ),
          ),
  );
  Future<void> _save() async {
    if (!key.currentState!.validate()) return;
    final items = quantities.entries
        .where((e) => (double.tryParse(e.value.text) ?? 0) > 0)
        .map(
          (e) => {'product_id': e.key, 'quantity': double.parse(e.value.text)},
        )
        .toList();
    if (items.isEmpty || photos.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Add at least one product quantity and one photograph.',
          ),
        ),
      );
      return;
    }
    setState(() => saving = true);
    final api = operationsApi(context);
    try {
      await api.upload(
        'operations/returns',
        {
          'order_id': order!['id'],
          'items': items,
          'reason': reason.text.trim(),
          'transport': transport.text.trim(),
        },
        files: {'photos[]': photos},
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) showOperationError(context, e);
    } finally {
      api.dispose();
      if (mounted) setState(() => saving = false);
    }
  }
}
