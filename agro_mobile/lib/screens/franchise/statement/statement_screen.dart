import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../providers/auth_provider.dart';
import '../../../services/api/api_service.dart';
import '../../../services/print_service.dart';
import '../../../widgets/common/app_card.dart';

/// Franchise account statement: opening balance, purchases (credit),
/// payments / credit notes / sales returns (debit) and the running balance.
class StatementScreen extends StatefulWidget {
  const StatementScreen({super.key});

  @override
  State<StatementScreen> createState() => _StatementScreenState();
}

class _StatementScreenState extends State<StatementScreen> {
  late DateTimeRange _range;
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _range = DateTimeRange(start: DateTime(now.year, now.month, 1), end: now);
    _load();
  }

  String _iso(DateTime d) => d.toIso8601String().substring(0, 10);

  static double _num(dynamic v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await context.read<ApiService>().getAccountStatement(
        from: _iso(_range.start),
        to: _iso(_range.end),
      );
      if (!mounted) return;
      setState(() => _data = data);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pickRange() async {
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2024),
      lastDate: DateTime.now(),
      initialDateRange: _range,
    );
    if (picked != null) {
      setState(() => _range = picked);
      _load();
    }
  }

  void _print() {
    final franchiseId = context.read<AuthProvider>().user?.franchiseId;
    if (franchiseId == null) return;
    PrintService.open(
      context,
      context.read<ApiService>(),
      'statement',
      franchiseId,
      query: {'from': _iso(_range.start), 'to': _iso(_range.end)},
    );
  }

  @override
  Widget build(BuildContext context) {
    final rows = (_data?['transactions'] as List?) ?? const [];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Account Statement'),
        actions: [
          IconButton(onPressed: _print, icon: const Icon(Icons.print_outlined), tooltip: 'Print'),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            OutlinedButton.icon(
              onPressed: _pickRange,
              icon: const Icon(Icons.date_range),
              label: Text('${Formatters.date(_range.start)} – ${Formatters.date(_range.end)}'),
            ),
            const SizedBox(height: 12),
            if (_loading && _data == null)
              const Padding(
                padding: EdgeInsets.all(32),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    TextButton(onPressed: _load, child: const Text('Retry')),
                  ],
                ),
              )
            else if (_data != null) ...[
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _summaryRow('Opening balance', _num(_data!['opening_balance'])),
                    _summaryRow('Purchases (credit)', _num((_data!['totals'] as Map?)?['credit'])),
                    _summaryRow('Payments & credits (debit)', _num((_data!['totals'] as Map?)?['debit'])),
                    const Divider(),
                    _summaryRow('Balance', _num(_data!['closing_balance']), bold: true),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              if (rows.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(24),
                  child: Center(child: Text('No transactions in this period.')),
                ),
              ...rows.whereType<Map>().map(_lineTile),
            ],
          ],
        ),
      ),
    );
  }

  Widget _summaryRow(String label, double value, {bool bold = false}) {
    final style = TextStyle(
      fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
      color: AppColors.textPrimary,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label, style: style), Text(Formatters.currency(value), style: style)],
      ),
    );
  }

  Widget _lineTile(Map row) {
    final credit = _num(row['credit']);
    final debit = _num(row['debit']);
    final isCredit = credit > 0;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(
          isCredit ? Icons.shopping_cart_outlined : Icons.payments_outlined,
          color: isCredit ? AppColors.warning : AppColors.success,
        ),
        title: Text(row['type_label']?.toString() ?? ''),
        subtitle: Text(
          '${row['date'] ?? ''} · ${row['reference_number'] ?? ''}\n${row['description'] ?? ''}',
        ),
        isThreeLine: true,
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              isCredit ? '+ ${Formatters.currency(credit)}' : '- ${Formatters.currency(debit)}',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                color: isCredit ? AppColors.warning : AppColors.success,
              ),
            ),
            Text(
              Formatters.currency(_num(row['balance'])),
              style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
    );
  }
}
