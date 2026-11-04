import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/enums/app_enums.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/order.dart';
import '../../../providers/order_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/status_badge.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';

class StaffOrderListScreen extends StatefulWidget {
  final OrderStatus? initialFilter;

  const StaffOrderListScreen({super.key, this.initialFilter});

  @override
  State<StaffOrderListScreen> createState() => _StaffOrderListScreenState();
}

class _StaffOrderListScreenState extends State<StaffOrderListScreen> {
  OrderStatus? _selectedFilter;

  @override
  void initState() {
    super.initState();
    _selectedFilter = widget.initialFilter;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<OrderProvider>();
      provider.loadOrders(silent: provider.orders.isNotEmpty);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Orders'),
        actions: [
          Consumer<OrderProvider>(
            builder: (context, provider, _) {
              final pendingCount = provider.orders
                  .where((o) => o.statusEnum == OrderStatus.pending)
                  .length;
              if (pendingCount == 0) return const SizedBox.shrink();
              return Badge(
                label: Text('$pendingCount'),
                backgroundColor: AppColors.warning,
                child: const IconButton(
                  icon: Icon(Icons.notifications_outlined),
                  onPressed: null,
                ),
              );
            },
          ),
        ],
      ),
      body: Consumer<OrderProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading) {
            return const LoadingView();
          }

          if (provider.error != null) {
            return ErrorView(
              message: provider.error!,
              onRetry: () => provider.loadOrders(),
            );
          }

          final filteredOrders = _selectedFilter == null
              ? provider.orders
              : provider.orders
                    .where((o) => o.statusEnum == _selectedFilter)
                    .toList();

          if (filteredOrders.isEmpty) {
            return const EmptyView(
              message: 'No orders found',
              icon: Icons.shopping_cart_outlined,
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.loadOrders(),
            child: Column(
              children: [
                _buildFilterChips(),
                _buildSummaryCards(provider),
                Expanded(
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: filteredOrders.length,
                    itemBuilder: (context, index) {
                      return _buildOrderCard(filteredOrders[index]);
                    },
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildFilterChips() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          _buildChip('All', null),
          const SizedBox(width: 8),
          _buildChip('Pending', OrderStatus.pending),
          const SizedBox(width: 8),
          _buildChip('Approved', OrderStatus.approved),
          const SizedBox(width: 8),
          _buildChip('Delivered', OrderStatus.delivered),
          const SizedBox(width: 8),
          _buildChip('Rejected', OrderStatus.declined),
        ],
      ),
    );
  }

  Widget _buildChip(String label, OrderStatus? status) {
    final isSelected = _selectedFilter == status;
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (_) {
        setState(() => _selectedFilter = status);
      },
      selectedColor: AppColors.primaryGreen,
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : AppColors.textPrimary,
      ),
      checkmarkColor: Colors.white,
    );
  }

  Widget _buildSummaryCards(OrderProvider provider) {
    final pendingCount = provider.orders
        .where((o) => o.statusEnum == OrderStatus.pending)
        .length;
    final approvedCount = provider.orders
        .where((o) => o.statusEnum == OrderStatus.approved)
        .length;
    final totalValue = provider.orders.fold<double>(
      0,
      (sum, o) => sum + o.totalAmount,
    );

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: StatCard(
              title: 'Pending',
              value: '$pendingCount',
              icon: Icons.pending_actions,
              iconColor: AppColors.warning,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: StatCard(
              title: 'Approved',
              value: '$approvedCount',
              icon: Icons.check_circle_outline,
              iconColor: AppColors.success,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: StatCard(
              title: 'Total Value',
              value: Formatters.currency(totalValue),
              icon: Icons.attach_money,
              iconColor: AppColors.primaryGreen,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderCard(Order order) {
    final isPending = order.statusEnum == OrderStatus.pending;
    final isDeclined = order.statusEnum == OrderStatus.declined;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: AppCard(
        onTap: () =>
            GoRouter.of(context).push('/staff/order-detail', extra: order.id),
        color: isPending
            ? AppColors.warning.withValues(alpha: 0.05)
            : isDeclined
            ? AppColors.error.withValues(alpha: 0.05)
            : null,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          order.franchiseName,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Order #${order.id}',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: AppColors.textSecondary),
                        ),
                      ],
                    ),
                  ),
                  StatusBadge.fromOrderStatus(order.status),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _buildInfoChip(
                    Icons.calendar_today,
                    Formatters.dateTime(order.createdAt),
                  ),
                  _buildInfoChip(
                    Icons.shopping_bag_outlined,
                    '${order.items.length} items',
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                Formatters.currency(order.totalAmount),
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.primaryGreen,
                ),
              ),
              if (isDeclined &&
                  order.declineReason != null &&
                  order.declineReason!.isNotEmpty) ...[
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.error.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: AppColors.error.withValues(alpha: 0.3),
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.report_problem_outlined,
                        size: 16,
                        color: AppColors.error,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          order.declineReason!,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textPrimary,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildInfoChip(IconData icon, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: AppColors.textSecondary),
        const SizedBox(width: 4),
        Text(
          label,
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
        ),
      ],
    );
  }
}
