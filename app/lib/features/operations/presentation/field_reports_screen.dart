import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/enums/user_role.dart';
import '../data/operations_api.dart';
import 'workspace_ui.dart';

const operationsPlatform = MethodChannel('farmmantra/operations');

class FieldZonesScreen extends StatefulWidget {
  const FieldZonesScreen({super.key});
  @override
  State<FieldZonesScreen> createState() => _FieldZonesScreenState();
}

class _FieldZonesScreenState extends State<FieldZonesScreen> {
  List<Map<String, dynamic>> zones = [];
  bool loading = true, busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    final api = operationsApi(context);
    try {
      final r = await api.get('operations/zones');
      if (mounted) {
        setState(() {
          zones = OperationsApi.rows(r);
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = operationError(e));
    } finally {
      api.dispose();
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final role = context.watch<AuthProvider>().userRole;
    return WorkspacePage(
      title: 'Field visits',
      actions: [
        if (role == UserRole.systemAdministrator)
          IconButton(
            tooltip: 'Add geofence',
            icon: const Icon(Icons.add_location_alt_outlined),
            onPressed: () async {
              await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ZoneFormScreen()),
              );
              load();
            },
          ),
      ],
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(22),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const Text(
                    'Check in where the work happens',
                    style: TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      color: workspaceGreen,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const Text(
                    'Location is requested only when you check in. A fresh, precise fix is checked against your assigned boundary. There is no background tracking.',
                    style: TextStyle(height: 1.6),
                  ),
                  const SizedBox(height: 22),
                  if (error != null) Text(error!),
                  if (zones.isEmpty)
                    const WorkspaceEmpty(
                      title: 'No assigned field locations',
                      message:
                          'Admin will add approved field locations and boundaries when the client supplies them.',
                      icon: Icons.location_on_outlined,
                    ),
                  for (final zone in zones)
                    Card(
                      margin: const EdgeInsets.only(bottom: 14),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(
                              Icons.location_on_outlined,
                              color: workspaceGreen,
                            ),
                            const SizedBox(height: 10),
                            Text(
                              zone['name'],
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text('Boundary radius: ${zone['radius_meters']} m'),
                            if (role == UserRole.fieldAgronomist)
                              Padding(
                                padding: const EdgeInsets.only(top: 14),
                                child: FilledButton.icon(
                                  onPressed: busy ? null : () => checkin(zone),
                                  icon: const Icon(Icons.my_location),
                                  label: Text(
                                    busy
                                        ? 'Obtaining precise location…'
                                        : 'Check in here',
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Future<void> checkin(Map<String, dynamic> zone) async {
    setState(() => busy = true);
    final api = operationsApi(context);
    try {
      final location = await operationsPlatform
          .invokeMapMethod<String, dynamic>('location');
      if (location == null) {
        throw PlatformException(
          code: 'LOCATION',
          message: 'No location available.',
        );
      }
      await api.post('operations/requests', {
        'type': 'checkin',
        'title': 'Visit · ${zone['name']}',
        'entry_date': DateTime.now().toIso8601String().split('T').first,
        'details': {
          'zone_id': zone['id'],
          'latitude': location['latitude'],
          'longitude': location['longitude'],
          'accuracy': location['accuracy'],
          'captured_at': DateTime.fromMillisecondsSinceEpoch(
            (location['timestamp'] as num).toInt(),
            isUtc: true,
          ).toIso8601String(),
        },
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Field check-in recorded.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              e is PlatformException
                  ? e.message ?? 'Location unavailable on this device.'
                  : operationError(e),
            ),
          ),
        );
      }
    } finally {
      api.dispose();
      if (mounted) setState(() => busy = false);
    }
  }
}

class ZoneFormScreen extends StatefulWidget {
  const ZoneFormScreen({super.key});
  @override
  State<ZoneFormScreen> createState() => _ZoneFormScreenState();
}

class _ZoneFormScreenState extends State<ZoneFormScreen> {
  final key = GlobalKey<FormState>();
  final fields = {
    for (final k in ['name', 'latitude', 'longitude', 'radius_meters'])
      k: TextEditingController(),
  };
  List<Map<String, dynamic>> users = [];
  String? user;
  bool saving = false;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = operationsApi(context);
    try {
      final r = await api.get('operations/options');
      if (mounted) {
        setState(
          () => users = List<Map<String, dynamic>>.from(
            (r['data']['users'] as List).where(
              (u) => u['role']?['name'] == 'Field Agronomist',
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) showOperationError(context, e);
    } finally {
      api.dispose();
    }
  }

  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Configure field boundary',
    child: Form(
      key: key,
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          const Text(
            'Enter the client-approved location and radius. No default boundary is assumed.',
          ),
          for (final e in fields.entries)
            Padding(
              padding: const EdgeInsets.only(top: 18),
              child: TextFormField(
                controller: e.value,
                keyboardType: e.key == 'name'
                    ? TextInputType.text
                    : const TextInputType.numberWithOptions(
                        decimal: true,
                        signed: true,
                      ),
                decoration: InputDecoration(
                  labelText: {
                    'name': 'Location name',
                    'latitude': 'Latitude',
                    'longitude': 'Longitude',
                    'radius_meters': 'Boundary radius (meters)',
                  }[e.key],
                ),
                validator: (v) => (v?.isEmpty ?? true) ? 'Required' : null,
              ),
            ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Assigned user'),
            items: [
              const DropdownMenuItem(
                value: null,
                child: Text('All field users'),
              ),
              ...users.map(
                (r) => DropdownMenuItem(
                  value: r['id'].toString(),
                  child: Text(r['name']),
                ),
              ),
            ],
            onChanged: (v) => user = v,
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: saving ? null : save,
            child: Text(saving ? 'Saving…' : 'Save field boundary'),
          ),
        ],
      ),
    ),
  );
  Future<void> save() async {
    if (!key.currentState!.validate()) return;
    setState(() => saving = true);
    final api = operationsApi(context);
    try {
      await api.post('operations/zones', {
        for (final e in fields.entries) e.key: e.value.text,
        'user_id': user,
      });
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) showOperationError(context, e);
    } finally {
      api.dispose();
      if (mounted) setState(() => saving = false);
    }
  }
}

class ExportReportsScreen extends StatefulWidget {
  const ExportReportsScreen({super.key, this.franchiseOnly = false});
  final bool franchiseOnly;
  @override
  State<ExportReportsScreen> createState() => _ExportReportsScreenState();
}

class _ExportReportsScreenState extends State<ExportReportsScreen> {
  String? busy;
  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Excel reports',
    child: ListView(
      padding: const EdgeInsets.all(22),
      children: [
        const Text(
          'Your business, ready to share',
          style: TextStyle(
            fontSize: 26,
            fontWeight: FontWeight.w800,
            color: workspaceGreen,
          ),
        ),
        const SizedBox(height: 10),
        const Text(
          'Download current records as Excel workbooks. Files are saved to Downloads/Farmmantra on Android.',
        ),
        const SizedBox(height: 24),
        for (final e in {
          if (!widget.franchiseOnly) 'warehouse': 'Central warehouse stock',
          'franchise_stock': 'Franchise stock',
          if (!widget.franchiseOnly) ...{
            'outstanding': 'Outstanding balances & ageing',
            'sales': 'Combined sales & margins',
            'expenses': 'Expense register',
          },
        }.entries)
          WorkspaceTile(
            title: e.value,
            subtitle: busy == e.key ? 'Preparing workbook…' : 'Download .xlsx',
            icon: Icons.table_chart_outlined,
            onTap: () {
              if (busy == null) download(e.key);
            },
          ),
      ],
    ),
  );
  Future<void> download(String report) async {
    setState(() => busy = report);
    final api = operationsApi(context);
    try {
      final bytes = await api.export(report);
      final path = await operationsPlatform.invokeMethod<String>(
        'saveWorkbook',
        {
          'bytes': bytes,
          'name':
              'farmmantra-$report-${DateTime.now().millisecondsSinceEpoch}.xlsx',
        },
      );
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Saved to $path')));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              e is MissingPluginException
                  ? 'Workbook saving is available in the Android app.'
                  : e is PlatformException
                  ? e.message ?? 'Unable to save workbook.'
                  : operationError(e),
            ),
          ),
        );
      }
    } finally {
      api.dispose();
      if (mounted) setState(() => busy = null);
    }
  }
}
