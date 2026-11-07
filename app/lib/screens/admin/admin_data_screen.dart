import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../features/operations/data/operations_api.dart';
import '../../features/operations/presentation/workspace_ui.dart';
import '../../core/utils/formatters.dart';

/// Admin-only master data editors. Other role folders never expose price editing.
class AdminDataScreen extends StatefulWidget {
  final String entity;
  const AdminDataScreen({super.key, required this.entity});
  @override
  State<AdminDataScreen> createState() => _AdminDataScreenState();
}

class _AdminDataScreenState extends State<AdminDataScreen> {
  final key = GlobalKey<RemoteRecordsState>();
  String get path => widget.entity == 'customers'
      ? 'operations/customers'
      : 'admin/${widget.entity}';
  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: {
      'products': 'Products & pricing',
      'franchises': 'Franchises',
      'users': 'User accounts',
      'customers': 'Farmers & retailers',
    }[widget.entity]!,
    actions: [
      IconButton(
        tooltip: 'Add record',
        icon: const Icon(Icons.add_circle_outline),
        onPressed: () => edit(null),
      ),
    ],
    child: RemoteRecords(
      key: key,
      path: path,
      builder: (context, r, reload) => Card(
        margin: const EdgeInsets.only(bottom: 12),
        elevation: 0,
        child: ListTile(
          contentPadding: const EdgeInsets.all(18),
          title: Text(
            r['name'] ?? '',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          subtitle: Text(
            widget.entity == 'products'
                ? '${r['sku']}\nFranchise ${Formatters.currency(r['standard_price'])} · Retail ${Formatters.currency(r['selling_price'])}\nFarmer ${Formatters.currency(r['farmer_price'])}\nBatch ${r['batch_number'] ?? '—'} · Expiry ${r['expires_at'] ?? '—'}'
                : widget.entity == 'users'
                ? '${r['email']}\n${r['role']?['name'] ?? ''}'
                : '${r['phone'] ?? ''} · ${r['region'] ?? r['franchise']?['name'] ?? ''}',
          ),
          trailing: ['products', 'franchises'].contains(widget.entity)
              ? IconButton(
                  tooltip: 'Edit record',
                  icon: const Icon(Icons.edit_outlined),
                  onPressed: () => edit(r),
                )
              : null,
        ),
      ),
    ),
  );
  Future<void> edit(Map<String, dynamic>? record) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AdminDataForm(entity: widget.entity, record: record),
      ),
    );
    key.currentState?.reload();
  }
}

class AdminDataForm extends StatefulWidget {
  final String entity;
  final Map<String, dynamic>? record;
  const AdminDataForm({super.key, required this.entity, this.record});
  @override
  State<AdminDataForm> createState() => _AdminDataFormState();
}

class _AdminDataFormState extends State<AdminDataForm> {
  final key = GlobalKey<FormState>();
  Map<String, dynamic> options = {};
  final Map<String, TextEditingController> fields = {};
  final Map<String, String?> selections = {};
  OperationsFile? image;
  bool loading = true, saving = false;
  String? error;
  Map<String, String> get labels => switch (widget.entity) {
    'products' => {
      'name': 'Product name',
      'sku': 'Product code',
      'standard_price': 'Franchise price (UGX)',
      'selling_price': 'Retailer price (UGX)',
      'farmer_price': 'Farmer price (UGX)',
      'unit_of_measure': 'Unit of measure',
      'batch_number': 'Batch number',
      'manufactured_at': 'Manufacturing date (YYYY-MM-DD)',
      'expires_at': 'Expiry date (YYYY-MM-DD)',
      'active_ingredient': 'Active ingredient',
      'description': 'Description',
    },
    'franchises' => {
      'name': 'Franchise name',
      'code': 'Franchise code',
      'contact_person': 'Contact person',
      'phone': 'Phone',
      'email': 'Email',
      'region': 'Zone / region',
      'address': 'Address',
      'credit_limit': 'Credit limit (UGX)',
    },
    'customers' => {
      'name': 'Customer name',
      'phone': 'Phone',
      'address': 'Address',
    },
    _ => {
      'name': 'Full name',
      'email': 'Email',
      'password': 'Initial password (8+ characters)',
      'phone': 'Phone',
    },
  };
  Map<String, String> get selects => switch (widget.entity) {
    'products' => {'category_id': 'categories'},
    'customers' => {
      'customer_type': 'customer_types',
      'franchise_id': 'franchises',
      'district_id': 'districts',
    },
    'users' => {'role_id': 'roles', 'franchise_id': 'franchises'},
    _ => {},
  };
  @override
  void initState() {
    super.initState();
    for (final field in labels.keys) {
      fields[field] = TextEditingController(
        text: widget.record?[field]?.toString() ?? '',
      );
    }
    for (final field in selects.keys) {
      selections[field] = widget.record?[field]?.toString();
    }
    _load();
  }

  Future<void> _load() async {
    final api = operationsApi(context);
    try {
      final r = await api.get('operations/options');
      if (mounted) {
        setState(
          () => options = {
            ...Map<String, dynamic>.from(r['data']),
            'customer_types': [
              {'id': 'farmer', 'name': 'Farmer'},
              {'id': 'retailer', 'name': 'Retailer'},
            ],
          },
        );
      }
    } catch (e) {
      if (mounted) setState(() => error = operationError(e));
    } finally {
      api.dispose();
      if (mounted) setState(() => loading = false);
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
    title: '${widget.record == null ? 'Add' : 'Edit'} ${widget.entity}',
    child: loading
        ? const Center(child: CircularProgressIndicator())
        : Form(
            key: key,
            child: ListView(
              padding: const EdgeInsets.all(22),
              children: [
                if (error != null) Text(error!),
                for (final field in labels.entries)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: TextFormField(
                      controller: fields[field.key],
                      obscureText: field.key == 'password',
                      keyboardType:
                          field.key.contains('price') ||
                              field.key == 'credit_limit'
                          ? const TextInputType.numberWithOptions(decimal: true)
                          : field.key == 'email'
                          ? TextInputType.emailAddress
                          : TextInputType.text,
                      maxLines: field.key == 'description' ? 3 : 1,
                      decoration: InputDecoration(labelText: field.value),
                      validator: (v) {
                        final required = [
                          'name',
                          if (widget.entity == 'products') ...[
                            'sku',
                            'standard_price',
                            'selling_price',
                            'farmer_price',
                          ],
                          if (widget.entity == 'franchises') ...[
                            'code',
                            'contact_person',
                            'phone',
                            'region',
                          ],
                          if (widget.entity == 'users') ...[
                            'email',
                            'password',
                          ],
                          if (widget.entity == 'customers') 'phone',
                        ].contains(field.key);
                        return required && (v?.trim().isEmpty ?? true)
                            ? 'Required'
                            : null;
                      },
                    ),
                  ),
                for (final field in selects.entries)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: DropdownButtonFormField<String>(
                      initialValue: selections[field.key],
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: field.key
                            .replaceAll('_id', '')
                            .replaceAll('_', ' '),
                      ),
                      items: [
                        if (field.key == 'franchise_id' &&
                            widget.entity == 'users')
                          const DropdownMenuItem(
                            value: null,
                            child: Text('No franchise'),
                          ),
                        ...((options[field.value] ?? []) as List).map(
                          (r) => DropdownMenuItem(
                            value: r['id'].toString(),
                            child: Text(
                              r['name'],
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                      ],
                      onChanged: (v) => selections[field.key] = v,
                      validator: (v) =>
                          !(field.key == 'franchise_id' &&
                                  widget.entity == 'users') &&
                              v == null
                          ? 'Choose a value'
                          : null,
                    ),
                  ),
                if (widget.entity == 'products')
                  OutlinedButton.icon(
                    onPressed: () async {
                      final f = await ImagePicker().pickImage(
                        source: ImageSource.gallery,
                        maxWidth: 1600,
                        imageQuality: 85,
                      );
                      if (f != null) {
                        final bytes = await f.readAsBytes();
                        if (mounted) {
                          setState(() => image = OperationsFile(f.name, bytes));
                        }
                      }
                    },
                    icon: const Icon(Icons.add_photo_alternate_outlined),
                    label: Text(image?.name ?? 'Upload actual product photo'),
                  ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: saving ? null : save,
                  child: Text(saving ? 'Saving…' : 'Save record'),
                ),
                const SizedBox(height: 24),
              ],
            ),
          ),
  );
  Future<void> save() async {
    if (!key.currentState!.validate()) return;
    setState(() => saving = true);
    final api = operationsApi(context);
    try {
      final path = widget.entity == 'customers'
          ? 'operations/customers'
          : 'admin/${widget.entity}${widget.record == null ? '' : '/${widget.record!['id']}'}';
      await api.upload(
        path,
        {
          for (final f in fields.entries)
            if (f.value.text.trim().isNotEmpty) f.key: f.value.text.trim(),
          ...selections,
          'is_active': true,
          if (widget.record != null) '_method': 'PUT',
        },
        files: {
          if (image != null) 'image': [image!],
        },
      );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) showOperationError(context, e);
    } finally {
      api.dispose();
      if (mounted) setState(() => saving = false);
    }
  }
}
