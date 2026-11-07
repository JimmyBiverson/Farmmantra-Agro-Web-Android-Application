import 'package:image_picker/image_picker.dart';
import '../data/operations_api.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/enums/user_role.dart';
import '../../../core/utils/formatters.dart';
import 'workspace_ui.dart';
import 'field_reports_screen.dart';

class RequestsScreen extends StatefulWidget {
  final String? initialType;
  const RequestsScreen({super.key, this.initialType});
  @override
  State<RequestsScreen> createState() => _RequestsScreenState();
}

class _RequestsScreenState extends State<RequestsScreen> {
  final listKey = GlobalKey<RemoteRecordsState>();
  String? type;
  @override
  void initState() {
    super.initState();
    type = widget.initialType;
  }

  @override
  Widget build(BuildContext context) {
    final role = context.watch<AuthProvider>().userRole;
    return WorkspacePage(
      title: 'Expenses & requests',
      actions: [
        IconButton(
          tooltip: 'New submission',
          icon: const Icon(Icons.add_circle_outline),
          onPressed: () => _create(context),
        ),
      ],
      child: Column(
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              children: [
                for (final entry in <String?, String>{
                  null: 'All',
                  'expense': 'Expenses',
                  'funds': 'Funds',
                  'travel': 'Travel',
                  'budget': 'Budgets',
                  'checkin': 'Visits',
                }.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(entry.value),
                      selected: type == entry.key,
                      onSelected: (_) => setState(() => type = entry.key),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RemoteRecords(
              key: listKey,
              path: 'operations/requests${type == null ? '' : '?type=$type'}',
              builder: (context, r, reload) {
                final status = r['status'];
                final kind = r['type'];
                final own =
                    r['user_id'].toString() ==
                    context.read<AuthProvider>().user?.id;
                final canApprove =
                    !own &&
                    status == 'submitted' &&
                    ((role == UserRole.systemAdministrator &&
                            (kind == 'funds' || kind == 'travel')) ||
                        (role == UserRole.financeDepartment &&
                            kind == 'expense'));
                final canPay =
                    !own &&
                    status == 'approved' &&
                    kind == 'funds' &&
                    role == UserRole.financeDepartment;
                final details = Map<String, dynamic>.from(r['details'] ?? {});
                return Card(
                  margin: const EdgeInsets.only(bottom: 14),
                  elevation: 0,
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                kind.toString().toUpperCase(),
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 1,
                                  color: workspaceGreen,
                                ),
                              ),
                            ),
                            StatusPill(status),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          r['title'],
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        if (num.tryParse(r['amount'].toString()) != 0)
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: Text(
                              Formatters.currency(r['amount']),
                              style: const TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        const SizedBox(height: 8),
                        Text(
                          '${r['user']?['name'] ?? 'You'} · ${r['entry_date'].toString().split('T').first}',
                        ),
                        if (details['notes'] != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(details['notes']),
                          ),
                        if (details['itinerary'] != null)
                          Text(details['itinerary']),
                        if (details['receipt'] != null)
                          TextButton.icon(
                            onPressed: () => _receipt(r),
                            icon: const Icon(Icons.attach_file),
                            label: const Text('View expense receipt'),
                          ),
                        if (r['history'] is List) ...[
                          for (final h in r['history'])
                            Padding(
                              padding: const EdgeInsets.only(top: 8),
                              child: Text(
                                '${h['action']} · ${h['name']}\n${h['notes']}${h['reference'] == null ? '' : ' · ${h['reference']}'}',
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: Color(0xff637269),
                                ),
                              ),
                            ),
                        ],
                        if (canApprove || canPay)
                          Padding(
                            padding: const EdgeInsets.only(top: 14),
                            child: Wrap(
                              spacing: 10,
                              children: [
                                if (canApprove)
                                  OutlinedButton(
                                    onPressed: () =>
                                        _review(r, 'reject', reload),
                                    child: const Text('Reject'),
                                  ),
                                FilledButton(
                                  onPressed: () => _review(
                                    r,
                                    canPay ? 'pay' : 'approve',
                                    reload,
                                  ),
                                  child: Text(
                                    canPay ? 'Record disbursement' : 'Approve',
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
          ),
        ],
      ),
    );
  }

  Future<void> _create(BuildContext context) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => RequestFormScreen(initialType: type)),
    );
    listKey.currentState?.reload();
  }

  Future<void> _receipt(Map<String, dynamic> record) async {
    final api = operationsApi(context);
    final path = 'operations/requests/${record['id']}/receipt';
    try {
      if (record['details']['receipt'].toString().toLowerCase().endsWith(
        '.pdf',
      )) {
        final bytes = await api.download(path);
        final saved = await operationsPlatform
            .invokeMethod<String>('saveDocument', {
              'bytes': bytes,
              'name': 'expense-${record['id']}.pdf',
              'mime': 'application/pdf',
            });
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text('Receipt saved to $saved')));
        }
      } else if (mounted) {
        openWorkspace(
          context,
          WorkspacePage(
            title: 'Expense receipt',
            child: InteractiveViewer(
              child: Image.network(
                api.uri(path).toString(),
                headers: api.headers,
                fit: BoxFit.contain,
                errorBuilder: (_, error, stack) => const Center(
                  child: Text('Unable to load receipt. Please try again.'),
                ),
              ),
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        showOperationError(context, e);
      }
    } finally {
      api.dispose();
    }
  }

  Future<void> _review(
    Map<String, dynamic> record,
    String action,
    VoidCallback reload,
  ) async {
    final notes = await reviewNotes(
      context,
      title: action == 'pay' ? 'Record funds paid' : 'Confirm $action',
      payment: action == 'pay',
    );
    if (notes == null || !mounted) return;
    final api = operationsApi(context);
    try {
      await api.post('operations/requests/${record['id']}/review', {
        'action': action,
        ...notes,
      });
      reload();
    } catch (e) {
      if (mounted) showOperationError(context, e);
    } finally {
      api.dispose();
    }
  }
}

class RequestFormScreen extends StatefulWidget {
  final String? initialType;
  const RequestFormScreen({super.key, this.initialType});
  @override
  State<RequestFormScreen> createState() => _RequestFormScreenState();
}

class _RequestFormScreenState extends State<RequestFormScreen> {
  final key = GlobalKey<FormState>();
  final title = TextEditingController(),
      amount = TextEditingController(),
      notes = TextEditingController(),
      itinerary = TextEditingController();
  String type = 'expense';
  DateTime date = DateTime.now(), week = DateTime.now();
  bool saving = false;
  String? fundId;
  OperationsFile? receipt;
  List<Map<String, dynamic>> funds = [];
  @override
  void initState() {
    super.initState();
    if (['expense', 'funds', 'travel', 'budget'].contains(widget.initialType)) {
      type = widget.initialType!;
    }
    _loadFunds();
  }

  Future<void> _loadFunds() async {
    final api = operationsApi(context);
    try {
      final r = await api.get('operations/requests?type=funds');
      if (mounted) {
        setState(
          () => funds = OperationsApi.rows(r)
              .where(
                (r) =>
                    r['status'] == 'paid' &&
                    r['user_id'].toString() ==
                        context.read<AuthProvider>().user?.id,
              )
              .toList(),
        );
      }
    } catch (_) {
      /* Linking a fund is optional; standalone expenses remain available. */
    } finally {
      api.dispose();
    }
  }

  @override
  void dispose() {
    for (final c in [title, amount, notes, itinerary]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final role = context.watch<AuthProvider>().userRole;
    final types = {
      'expense': 'Daily expense',
      'funds': 'Request funds',
      if (role == UserRole.salesHead || role == UserRole.fieldAgronomist)
        'travel': 'Weekly travel plan',
      if (role == UserRole.systemAdministrator) 'budget': 'Monthly budget',
    };
    if (!types.containsKey(type)) {
      type = 'expense';
    }
    return WorkspacePage(
      title: 'New submission',
      child: Form(
        key: key,
        child: ListView(
          padding: const EdgeInsets.all(22),
          children: [
            Text(
              'Keep every operation accountable',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 8),
            const Text(
              'Submit once. Follow the review and payment status here.',
            ),
            const SizedBox(height: 24),
            DropdownButtonFormField<String>(
              initialValue: type,
              items: types.entries
                  .map(
                    (e) => DropdownMenuItem(value: e.key, child: Text(e.value)),
                  )
                  .toList(),
              onChanged: saving ? null : (v) => setState(() => type = v!),
              decoration: const InputDecoration(labelText: 'Submission type'),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: title,
              decoration: const InputDecoration(labelText: 'Title / purpose'),
              validator: (v) =>
                  (v?.trim().isEmpty ?? true) ? 'Enter a purpose' : null,
            ),
            const SizedBox(height: 16),
            if (type != 'travel') ...[
              TextFormField(
                controller: amount,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(labelText: 'Amount (UGX)'),
                validator: (v) => (double.tryParse(v ?? '') ?? 0) <= 0
                    ? 'Enter an amount greater than zero'
                    : null,
              ),
              const SizedBox(height: 16),
            ],
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Entry date'),
              subtitle: Text(Formatters.date(date)),
              trailing: const Icon(Icons.calendar_today_outlined),
              onTap: () async {
                final d = await showDatePicker(
                  context: context,
                  initialDate: date,
                  firstDate: DateTime(2020),
                  lastDate: DateTime.now().add(const Duration(days: 365)),
                );
                if (d != null) setState(() => date = d);
              },
            ),
            if (type == 'travel') ...[
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Week starting Sunday'),
                subtitle: Text(Formatters.date(week)),
                trailing: const Icon(Icons.calendar_month),
                onTap: () async {
                  final d = await showDatePicker(
                    context: context,
                    initialDate: week,
                    firstDate: DateTime(2020),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                  );
                  if (d != null) setState(() => week = d);
                },
              ),
              TextFormField(
                controller: itinerary,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Daily destinations, activities and travel needs',
                ),
                validator: (v) =>
                    type == 'travel' && (v?.trim().isEmpty ?? true)
                    ? 'Enter your itinerary'
                    : null,
              ),
            ],
            if (type == 'expense' && funds.isNotEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: DropdownButtonFormField<String>(
                  initialValue: fundId,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Reconcile disbursed funds (optional)',
                  ),
                  items: [
                    const DropdownMenuItem(
                      value: null,
                      child: Text('Standalone expense'),
                    ),
                    ...funds.map(
                      (r) => DropdownMenuItem(
                        value: r['id'].toString(),
                        child: Text(
                          r['title'],
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                  ],
                  onChanged: (v) => setState(() => fundId = v),
                ),
              ),
            const SizedBox(height: 16),
            TextFormField(
              controller: notes,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Details / expense category / explanation',
              ),
              validator: (v) => (v?.trim().isEmpty ?? true)
                  ? 'Add a short explanation'
                  : null,
            ),
            const SizedBox(height: 24),
            if (type == 'expense')
              OutlinedButton.icon(
                onPressed: () async {
                  final file = await ImagePicker().pickImage(
                    source: ImageSource.gallery,
                    maxWidth: 1800,
                    imageQuality: 80,
                  );
                  if (file != null) {
                    final bytes = await file.readAsBytes();
                    if (mounted) {
                      setState(
                        () => receipt = OperationsFile(file.name, bytes),
                      );
                    }
                  }
                },
                icon: const Icon(Icons.attach_file),
                label: Text(
                  receipt?.name ?? 'Attach receipt photograph (optional',
                ),
              ),
            FilledButton.icon(
              onPressed: saving ? null : _save,
              icon: saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send_rounded),
              label: Text(saving ? 'Submitting…' : 'Submit for review'),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Future<void> _save() async {
    if (!key.currentState!.validate()) return;
    if (type == 'travel' && week.weekday != DateTime.sunday) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Choose Sunday as the travel week start.'),
        ),
      );
      return;
    }
    setState(() => saving = true);
    final api = operationsApi(context);
    try {
      await api.upload(
        'operations/requests',
        {
          'type': type,
          'title': title.text.trim(),
          'amount': double.tryParse(amount.text) ?? 0,
          'entry_date': date.toIso8601String().split('T').first,
          if (type == 'expense' && fundId != null) 'fund_request_id': fundId,
          'details': {
            'notes': notes.text.trim(),
            if (type == 'travel')
              'week_start': week.toIso8601String().split('T').first,
            if (type == 'travel') 'itinerary': itinerary.text.trim(),
          },
        },
        files: {
          if (receipt != null && type == 'expense') 'receipt': [receipt!],
        },
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
