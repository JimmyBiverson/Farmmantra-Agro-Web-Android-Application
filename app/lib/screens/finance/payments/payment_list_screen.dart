import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../providers/payment_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/status_badge.dart';
import '../../../widgets/common/loading_view.dart';

class PaymentListScreen extends StatefulWidget {
  final String? statusFilter;

  const PaymentListScreen({super.key, this.statusFilter});

  @override
  State<PaymentListScreen> createState() => _PaymentListScreenState();
}

class _PaymentListScreenState extends State<PaymentListScreen> {
  String? _selectedStatus;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _selectedStatus = widget.statusFilter;
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadData());
  }

  Future<void> _loadData() async {
    final provider = context.read<PaymentProvider>();
    final silent = provider.payments.isNotEmpty;
    if (!silent) setState(() => _isLoading = true);
    await provider.loadFinancePayments(status: _selectedStatus, silent: silent);
    if (mounted) setState(() => _isLoading = false);
  }

  void _onFilterChanged(String? status) {
    setState(() => _selectedStatus = status);
    _loadData();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _buildFilterChips(),
        Expanded(
          child: _isLoading
              ? const Center(child: LoadingView())
              : _buildPaymentList(),
        ),
      ],
    );
  }

  Widget _buildFilterChips() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            _buildChip('All', null),
            const SizedBox(width: 8),
            _buildChip('Pending', 'pending'),
            const SizedBox(width: 8),
            _buildChip('Verified', 'verified'),
            const SizedBox(width: 8),
            _buildChip('Accepted', 'accepted'),
            const SizedBox(width: 8),
            _buildChip('Rejected', 'rejected'),
          ],
        ),
      ),
    );
  }

  Widget _buildChip(String label, String? status) {
    final isSelected = _selectedStatus == status;
    return FilterChip(
      label: Text(
        label,
        style: TextStyle(
          fontSize: 13,
          fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
          color: isSelected ? Colors.white : AppColors.textPrimary,
        ),
      ),
      selected: isSelected,
      selectedColor: AppColors.primaryGreen,
      backgroundColor: AppColors.surfaceWhite,
      checkmarkColor: Colors.white,
      side: BorderSide(
        color: isSelected ? AppColors.primaryGreen : AppColors.divider,
      ),
      onSelected: (_) => _onFilterChanged(status),
      padding: const EdgeInsets.symmetric(horizontal: 4),
    );
  }

  Widget _buildPaymentList() {
    final payments = context.watch<PaymentProvider>().payments;

    if (payments.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.payments_outlined, size: 48, color: AppColors.textLight),
            const SizedBox(height: 16),
            Text(
              _selectedStatus != null
                  ? 'No ${_selectedStatus!} payments'
                  : 'No payments found',
              style: const TextStyle(
                fontSize: 16,
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadData,
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        itemCount: payments.length,
        itemBuilder: (context, index) {
          final payment = payments[index];
          return AppCard(
            onTap: () {
              GoRouter.of(
                context,
              ).push('/finance/payment-detail', extra: payment.id);
            },
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: _paymentStatusColor(payment.status).withAlpha(26),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    _paymentStatusIcon(payment.status),
                    color: _paymentStatusColor(payment.status),
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        payment.paymentNumber ?? payment.id,
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        payment.franchiseName.isNotEmpty
                            ? payment.franchiseName
                            : 'Franchise #${payment.franchiseId}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                      if (payment.transactionReference.isNotEmpty)
                        Text(
                          'Ref: ${payment.transactionReference}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.textLight,
                          ),
                        ),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      Formatters.currency(payment.amount),
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 4),
                    StatusBadge.fromPaymentStatus(payment.status),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Color _paymentStatusColor(String status) {
    switch (status) {
      case 'pending':
        return AppColors.warning;
      case 'verified':
        return AppColors.info;
      case 'accepted':
        return AppColors.success;
      case 'rejected':
        return AppColors.error;
      default:
        return AppColors.textLight;
    }
  }

  IconData _paymentStatusIcon(String status) {
    switch (status) {
      case 'pending':
        return Icons.pending_actions;
      case 'verified':
        return Icons.verified_outlined;
      case 'accepted':
        return Icons.check_circle_outline;
      case 'rejected':
        return Icons.cancel_outlined;
      default:
        return Icons.help_outline;
    }
  }
}
