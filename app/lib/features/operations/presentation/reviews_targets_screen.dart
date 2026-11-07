import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/enums/user_role.dart';
import '../../../core/utils/formatters.dart';
import 'workspace_ui.dart';

class OrderReviewsScreen extends StatelessWidget {
  const OrderReviewsScreen({super.key});
  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Order approvals',
    child: RemoteRecords(
      path: 'staff/orders?status=pending',
      emptyTitle: 'You’re all caught up',
      emptyMessage:
          'New orders appear here when a franchise submits a purchase.',
      builder: (context, r, reload) {
        final role = context.read<AuthProvider>().userRole;
        final stage = r['review_stage'];
        final canAct =
            (stage == 'sales_head' && role == UserRole.salesHead) ||
            (stage == 'admin' && role == UserRole.systemAdministrator);
        return Card(
          elevation: 0,
          margin: const EdgeInsets.only(bottom: 16),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        r['order_number'],
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                    StatusPill(stage ?? r['status']),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  r['franchise']?['name'] ?? 'Franchise',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                Text(
                  Formatters.currency(r['total_amount']),
                  style: const TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: workspaceGreen,
                  ),
                ),
                const Divider(height: 28),
                for (final line in r['items'] ?? [])
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(
                      '${line['product']?['name']} · ${line['quantity']} × ${Formatters.currency(line['unit_price'])}',
                    ),
                  ),
                if (r['notes'] != null) Text(r['notes']),
                for (final history in r['review_history'] ?? [])
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      '${history['name']} · ${history['notes']}',
                      style: const TextStyle(color: Color(0xff637269)),
                    ),
                  ),
                if (canAct)
                  Padding(
                    padding: const EdgeInsets.only(top: 18),
                    child: Wrap(
                      spacing: 12,
                      children: [
                        OutlinedButton(
                          onPressed: () => _review(context, r, false, reload),
                          child: const Text('Decline'),
                        ),
                        FilledButton(
                          onPressed: () => _review(context, r, true, reload),
                          child: const Text('Approve & forward'),
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
  Future<void> _review(
    BuildContext context,
    Map<String, dynamic> r,
    bool approve,
    VoidCallback reload,
  ) async {
    final notes = await reviewNotes(
      context,
      title: approve ? 'Approve purchase order' : 'Decline purchase order',
    );
    if (notes == null || !context.mounted) return;
    final api = operationsApi(context);
    try {
      await api.post('operations/orders/${r['id']}/review', {
        'approve': approve,
        'notes': notes['notes'],
      });
      reload();
    } catch (e) {
      if (context.mounted) showOperationError(context, e);
    } finally {
      api.dispose();
    }
  }
}

class TargetsScreen extends StatefulWidget {
  const TargetsScreen({super.key});
  @override
  State<TargetsScreen> createState() => _TargetsScreenState();
}

class _TargetsScreenState extends State<TargetsScreen> {
  final key = GlobalKey<RemoteRecordsState>();
  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Sales targets',
    actions: [
      if (context.watch<AuthProvider>().userRole ==
          UserRole.systemAdministrator)
        IconButton(
          tooltip: 'Set sales target',
          icon: const Icon(Icons.add_circle_outline),
          onPressed: () async {
            await Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const TargetFormScreen()),
            );
            key.currentState?.reload();
          },
        ),
    ],
    child: RemoteRecords(
      key: key,
      path: 'operations/targets',
      emptyTitle: 'Ready for the next milestone',
      emptyMessage:
          'Admin can set weekly, monthly and yearly targets for each franchise and Sales Head.',
      builder: (context, r, reload) {
        final pct = double.tryParse(r['achievement_percentage'].toString());
        return Card(
          margin: const EdgeInsets.only(bottom: 16),
          elevation: 0,
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${r['period'] ?? 'Monthly'} target · Franchise #${r['franchise_id']}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                Text(
                  '${r['starts_on'] ?? '${r['month']}/${r['year']}'} — ${r['ends_on'] ?? 'End of month'}',
                ),
                const SizedBox(height: 18),
                Text(
                  pct == null
                      ? 'No target amount'
                      : '${pct.toStringAsFixed(1)}% achieved',
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                    color: workspaceGreen,
                  ),
                ),
                const SizedBox(height: 12),
                LinearProgressIndicator(
                  value: pct == null ? 0 : (pct / 100).clamp(0, 1),
                  minHeight: 8,
                  borderRadius: BorderRadius.circular(10),
                ),
                const SizedBox(height: 12),
                Text(
                  '${Formatters.currency(r['actual_amount'])} / ${Formatters.currency(r['target_amount'])}',
                ),
                if (r['product_id'] != null)
                  Text('Product #${r['product_id']}'),
              ],
            ),
          ),
        );
      },
    ),
  );
}

class TargetFormScreen extends StatefulWidget {
  const TargetFormScreen({super.key});
  @override
  State<TargetFormScreen> createState() => _TargetFormScreenState();
}

class _TargetFormScreenState extends State<TargetFormScreen> {
  final key = GlobalKey<FormState>();
  final amount = TextEditingController();
  String? franchise, user, product;
  String period = 'monthly';
  DateTime start = DateTime.now(),
      end = DateTime.now().add(const Duration(days: 30));
  Map<String, dynamic> options = {};
  bool saving = false;
  String? error;
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
          () => options = {
            ...Map<String, dynamic>.from(r['data']),
            'users': (r['data']['users'] as List)
                .where((u) => u['role']?['name'] == 'Sales Head')
                .toList(),
          },
        );
      }
    } catch (e) {
      if (mounted) setState(() => error = operationError(e));
    } finally {
      api.dispose();
    }
  }

  @override
  void dispose() {
    amount.dispose();
    super.dispose();
  }

  Widget select(
    String label,
    String field,
    String? value,
    ValueChanged<String?> changed, {
    bool required = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: DropdownButtonFormField<String>(
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: [
        if (!required)
          const DropdownMenuItem(value: null, child: Text('All / none')),
        ...((options[field] ?? []) as List).map(
          (r) => DropdownMenuItem(
            value: r['id'].toString(),
            child: Text(r['name'], overflow: TextOverflow.ellipsis),
          ),
        ),
      ],
      onChanged: changed,
      validator: (v) => required && v == null ? 'Choose $label' : null,
    ),
  );
  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Set a sales target',
    child: Form(
      key: key,
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          if (error != null) Text(error!),
          select(
            'Franchise',
            'franchises',
            franchise,
            (v) => franchise = v,
            required: true,
          ),
          select('Sales Head (optional)', 'users', user, (v) => user = v),
          select('Product (optional)', 'products', product, (v) => product = v),
          DropdownButtonFormField(
            initialValue: period,
            decoration: const InputDecoration(labelText: 'Period'),
            items: [
              'weekly',
              'monthly',
              'yearly',
            ].map((v) => DropdownMenuItem(value: v, child: Text(v))).toList(),
            onChanged: (v) => setState(() => period = v!),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: amount,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Target amount (UGX)'),
            validator: (v) => (double.tryParse(v ?? '') ?? 0) <= 0
                ? 'Enter a positive target'
                : null,
          ),
          for (final isStart in [true, false])
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(isStart ? 'Start date' : 'End date'),
              subtitle: Text(Formatters.date(isStart ? start : end)),
              trailing: const Icon(Icons.calendar_month),
              onTap: () async {
                final d = await showDatePicker(
                  context: context,
                  initialDate: isStart ? start : end,
                  firstDate: DateTime(2020),
                  lastDate: DateTime(2040),
                );
                if (d != null) {
                  setState(() {
                    if (isStart) {
                      start = d;
                    } else {
                      end = d;
                    }
                  });
                }
              },
            ),
          const SizedBox(height: 18),
          FilledButton(
            onPressed: saving ? null : _save,
            child: Text(saving ? 'Saving…' : 'Save target'),
          ),
        ],
      ),
    ),
  );
  Future<void> _save() async {
    if (!key.currentState!.validate()) return;
    setState(() => saving = true);
    final api = operationsApi(context);
    try {
      await api.post('operations/targets', {
        'franchise_id': franchise,
        'user_id': user,
        'product_id': product,
        'target_amount': double.parse(amount.text),
        'period': period,
        'starts_on': start.toIso8601String().split('T').first,
        'ends_on': end.toIso8601String().split('T').first,
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
