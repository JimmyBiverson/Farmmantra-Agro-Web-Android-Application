import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/enums/app_enums.dart';
import '../../core/utils/formatters.dart';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../widgets/common/app_card.dart';
import '../../widgets/common/section_header.dart';
import '../../widgets/common/status_badge.dart';
import '../../widgets/common/loading_view.dart';
import 'staff_shell.dart';

class StaffDashboard extends StatefulWidget {
  const StaffDashboard({super.key});

  @override
  State<StaffDashboard> createState() => _StaffDashboardState();
}

class _StaffDashboardState extends State<StaffDashboard> {
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadData());
  }

  Future<void> _loadData() async {
    try {
      await Future.wait([
        context.read<OrderProvider>().loadOrders(),
        context.read<OrderProvider>().loadStaffDashboard(),
      ]);
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildWelcomeHeader(),
          const SizedBox(height: 20),
          if (_isLoading)
            const SizedBox(height: 200, child: Center(child: LoadingView()))
          else ...[
            _buildOperationsBanner(),
            const SizedBox(height: 8),
            _buildQuickActions(),
            const SizedBox(height: 8),
            _buildDeliverySummary(),
            const SizedBox(height: 16),
            SectionHeader(
              title: 'Pending Orders',
              actionLabel: 'View All',
              onAction: () {
                StaffTabScope.of(
                  context,
                )?.onSwitchToOrders(OrderStatus.pending);
              },
            ),
            _buildPendingOrders(),
            const SizedBox(height: 24),
          ],
        ],
      ),
    );
  }

  Widget _buildOperationsBanner() {
    final dashboard = context.watch<OrderProvider>().staffDashboard;
    final summary = dashboard['summary'] ?? {};
    final awaitingPayment = (summary['awaiting_payment_orders'] ?? 0) as num;
    final deliveriesToday = (summary['deliveries_today'] ?? 0) as num;
    final newMessages = (summary['new_support_messages'] ?? 0) as num;

    if (awaitingPayment == 0 && deliveriesToday == 0 && newMessages == 0) {
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
                'Today\'s Operations',
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
              _buildBannerItem(
                Icons.payments_outlined,
                '${awaitingPayment.toInt()}',
                'Awaiting\npayment',
              ),
              _buildBannerDivider(),
              _buildBannerItem(
                Icons.local_shipping_outlined,
                '${deliveriesToday.toInt()}',
                'Deliveries\ntoday',
              ),
              _buildBannerDivider(),
              _buildBannerItem(
                Icons.headset_mic_outlined,
                '${newMessages.toInt()}',
                'New support\nmessages',
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBannerItem(IconData icon, String count, String label) {
    return Expanded(
      child: Column(
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
      ),
    );
  }

  Widget _buildBannerDivider() {
    return Container(width: 1, height: 48, color: Colors.white24);
  }

  Widget _buildWelcomeHeader() {
    final user = context.watch<AuthProvider>().user;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Hello, ${user?.name ?? 'Staff'}',
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

  Widget _buildQuickActions() {
    final allOrders = context.read<OrderProvider>().orders;
    final pendingCount = allOrders
        .where((o) => o.statusEnum == OrderStatus.pending)
        .length;
    final approvedToday = allOrders
        .where((o) => o.statusEnum == OrderStatus.approved)
        .length;

    return Row(
      children: [
        Expanded(
          child: _buildQuickActionCard(
            'Pending\nOrders',
            '$pendingCount',
            Icons.pending_actions,
            AppColors.warning,
            onTap: () => StaffTabScope.of(
              context,
            )?.onSwitchToOrders(OrderStatus.pending),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _buildQuickActionCard(
            'Total\nOrders',
            '${allOrders.length}',
            Icons.receipt_long,
            AppColors.info,
            onTap: () => StaffTabScope.of(
              context,
            )?.onSwitchToOrders(OrderStatus.approved),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _buildQuickActionCard(
            'Approved',
            '$approvedToday',
            Icons.check_circle_outline,
            AppColors.primaryGreen,
            onTap: () => StaffTabScope.of(context)?.onSwitchToOrders(null),
          ),
        ),
      ],
    );
  }

  Widget _buildQuickActionCard(
    String label,
    String count,
    IconData icon,
    Color color, {
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
          ],
        ),
      ),
    );
  }

  Widget _buildDeliverySummary() {
    final orders = context.watch<OrderProvider>().orders;
    final pending = orders
        .where(
          (o) =>
              o.statusEnum == OrderStatus.approved &&
              !o.isFullyCompleted &&
              !o.isOutForDelivery,
        )
        .length;
    final successful = orders.where((o) => o.isFullyCompleted).length;

    return Row(
      children: [
        Expanded(
          child: _buildQuickActionCard(
            'Pending\nDeliveries',
            '$pending',
            Icons.local_shipping_outlined,
            AppColors.info,
            onTap: () => StaffTabScope.of(
              context,
            )?.onSwitchToOrders(OrderStatus.approved),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _buildQuickActionCard(
            'Successful\nDeliveries',
            '$successful',
            Icons.task_alt,
            AppColors.success,
            onTap: () => StaffTabScope.of(
              context,
            )?.onSwitchToOrders(OrderStatus.delivered),
          ),
        ),
      ],
    );
  }

  Widget _buildPendingOrders() {
    final pendingOrders = context
        .read<OrderProvider>()
        .orders
        .where((o) => o.statusEnum == OrderStatus.pending)
        .take(5)
        .toList();

    if (pendingOrders.isEmpty) {
      return const AppCard(
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'No pending orders',
              style: TextStyle(color: AppColors.success),
            ),
          ),
        ),
      );
    }

    return Column(
      children: pendingOrders.map((order) {
        return AppCard(
          onTap: () {
            GoRouter.of(context).push('/staff/order-detail', extra: order.id);
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
                      order.orderNumber ?? order.id,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${order.franchiseName} \u2022 ${order.items.length} items',
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
                    Formatters.currency(order.totalAmount),
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 4),
                  StatusBadge.fromOrderStatus(order.status),
                ],
              ),
            ],
          ),
        );
      }).toList(),
    );
  }
}
