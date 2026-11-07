import '../../features/operations/presentation/operations_home.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/enums/app_enums.dart';
import '../../core/utils/formatters.dart';
import '../../providers/auth_provider.dart';
import '../../providers/payment_provider.dart';
import '../../widgets/common/app_card.dart';
import '../../widgets/common/section_header.dart';
import '../../widgets/common/status_badge.dart';
import '../../widgets/common/loading_view.dart';
import 'finance_shell.dart';

class FinanceDashboard extends StatefulWidget {
  const FinanceDashboard({super.key});

  @override
  State<FinanceDashboard> createState() => _FinanceDashboardState();
}

class _FinanceDashboardState extends State<FinanceDashboard> {
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadData());
  }

  Future<void> _loadData() async {
    final pp = context.read<PaymentProvider>();
    await Future.wait([
      pp.loadFinanceDashboard(),
      pp.loadPendingFinancePayments(),
      pp.loadFinancePayments(),
    ]);
    if (mounted) setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async {
        setState(() => _isLoading = true);
        await _loadData();
      },
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildWelcomeHeader(),
            const SizedBox(height: 16),
            const OperationsHome(compact: true),
            const SizedBox(height: 20),
            if (_isLoading)
              const SizedBox(height: 200, child: Center(child: LoadingView()))
            else ...[
              _buildOperationsBanner(),
              const SizedBox(height: 8),
              _buildSummaryCards(),
              const SizedBox(height: 8),
              SectionHeader(
                title: 'Pending Payments',
                actionLabel: 'View All',
                onAction: () {
                  FinanceTabScope.of(context)?.onSwitchTab(1);
                },
              ),
              _buildPendingPayments(),
              const SizedBox(height: 24),
              SectionHeader(
                title: 'Recent Activity',
                actionLabel: 'View All',
                onAction: () {
                  FinanceTabScope.of(context)?.onSwitchTab(2);
                },
              ),
              _buildRecentPayments(),
              const SizedBox(height: 24),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildWelcomeHeader() {
    final user = context.watch<AuthProvider>().user;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Hello, ${user?.name ?? 'Finance'}',
          style: const TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          Formatters.dateTime(DateTime.now()),
          style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
        ),
      ],
    );
  }

  Widget _buildOperationsBanner() {
    final dashboard = context.read<PaymentProvider>().financeDashboard;
    final summary = dashboard['summary'] ?? {};
    final payments = context.read<PaymentProvider>().payments;
    final infoRequests = payments
        .where((p) => p.statusEnum == PaymentStatus.infoRequested)
        .length;
    final pendingTotal = Formatters.currency(
      double.tryParse(summary['pending_payments_total']?.toString() ?? '0') ??
          0,
    );

    if (infoRequests == 0 && pendingTotal == 'UGX 0') {
      return const SizedBox.shrink();
    }

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primaryGreenDark, AppColors.primaryGreen],
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.insights, color: Colors.white, size: 20),
              SizedBox(width: 8),
              Text(
                'Approval Queue',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _buildBannerItem(
                  Icons.account_balance_wallet_outlined,
                  pendingTotal,
                  'Pending payments\nvalue',
                ),
              ),
              _buildBannerDivider(),
              Expanded(
                child: _buildBannerItem(
                  Icons.help_outline,
                  '$infoRequests',
                  'Awaiting info\nrequests',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBannerItem(IconData icon, String count, String label) {
    return Column(
      children: [
        Icon(icon, color: Colors.white, size: 22),
        const SizedBox(height: 6),
        Text(
          count,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 11,
            height: 1.2,
          ),
        ),
      ],
    );
  }

  Widget _buildBannerDivider() {
    return Container(width: 1, height: 48, color: Colors.white24);
  }

  Widget _buildSummaryCards() {
    final dashboard = context.read<PaymentProvider>().financeDashboard;
    final summary = dashboard['summary'] ?? {};

    final pendingCount = (summary['pending_payments_count'] ?? 0).toString();
    final pendingTotal = Formatters.currency(
      double.tryParse(summary['pending_payments_total']?.toString() ?? '0') ??
          0,
    );
    final acceptedThisMonth = (summary['accepted_this_month'] ?? 0).toString();
    final totalOutstanding = Formatters.currency(
      double.tryParse(summary['total_outstanding']?.toString() ?? '0') ?? 0,
    );
    final totalCollected = Formatters.currency(
      double.tryParse(summary['total_collected_ytd']?.toString() ?? '0') ?? 0,
    );

    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _buildStatCard(
                'Pending\nPayments',
                pendingCount,
                Icons.pending_actions,
                AppColors.warning,
                subtitle: pendingTotal,
                onTap: () => FinanceTabScope.of(context)?.onSwitchTab(1),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard(
                'Accepted\nThis Month',
                acceptedThisMonth,
                Icons.check_circle_outline,
                AppColors.success,
                onTap: () => FinanceTabScope.of(context)?.onSwitchTab(2),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _buildStatCard(
                'Outstanding\nReceivables',
                '',
                Icons.account_balance_wallet_outlined,
                AppColors.error,
                subtitle: totalOutstanding,
                onTap: () => FinanceTabScope.of(context)?.onSwitchTab(2),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard(
                'Collected\nYTD',
                '',
                Icons.savings_outlined,
                AppColors.primaryGreen,
                subtitle: totalCollected,
                onTap: () => FinanceTabScope.of(context)?.onSwitchTab(2),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildStatCard(
    String label,
    String count,
    IconData icon,
    Color color, {
    String? subtitle,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surfaceWhite,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.divider),
        ),
        child: Column(
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 8),
            if (count.isNotEmpty)
              Text(
                count,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
            const SizedBox(height: 4),
            Text(
              label,
              style: const TextStyle(
                fontSize: 11,
                color: AppColors.textSecondary,
                height: 1.2,
              ),
              textAlign: TextAlign.center,
            ),
            if (subtitle != null) ...[
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: color,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildPendingPayments() {
    final pending = context.read<PaymentProvider>().pendingFinancePayments;

    if (pending.isEmpty) {
      return const AppCard(
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'No pending payments',
              style: TextStyle(color: AppColors.success),
            ),
          ),
        ),
      );
    }

    return Column(
      children: pending.take(5).map((payment) {
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
                  color: AppColors.warning.withAlpha(26),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.pending_actions,
                  color: AppColors.warning,
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
      }).toList(),
    );
  }

  Widget _buildRecentPayments() {
    final payments = context.read<PaymentProvider>().payments;
    final recent = payments
        .where((p) => p.statusEnum != PaymentStatus.pending)
        .take(5)
        .toList();

    if (recent.isEmpty) {
      return const AppCard(
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'No recent activity',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
        ),
      );
    }

    return Column(
      children: recent.map((payment) {
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
      }).toList(),
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
