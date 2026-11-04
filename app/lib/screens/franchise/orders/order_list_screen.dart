import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/enums/app_enums.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/order.dart';
import '../../../providers/order_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';
import '../../../widgets/common/status_badge.dart';
import 'order_detail_screen.dart';

class OrderListScreen extends StatefulWidget {
  const OrderListScreen({super.key});

  @override
  State<OrderListScreen> createState() => _OrderListScreenState();
}

class _OrderListScreenState extends State<OrderListScreen> {
  OrderStatus? _selectedFilter;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<OrderProvider>();
      provider.loadOrders(silent: provider.orders.isNotEmpty);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _buildFilterChips(),
        const SizedBox(height: AppConstants.smallPadding),
        Expanded(child: _buildOrderList()),
      ],
    );
  }

  Widget _buildFilterChips() {
    final filters = [
      (null as OrderStatus?, 'All'),
      (OrderStatus.pending, 'Pending'),
      (OrderStatus.approved, 'Approved'),
      (OrderStatus.adjusted, 'Adjusted'),
      (OrderStatus.declined, 'Declined'),
      (OrderStatus.delivered, 'Delivered'),
      (OrderStatus.cancelled, 'Cancelled'),
    ];

    return SizedBox(
      height: 44,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(
          horizontal: AppConstants.defaultPadding,
        ),
        scrollDirection: Axis.horizontal,
        itemCount: filters.length,
        separatorBuilder: (context, index) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final (status, label) = filters[index];
          final isSelected = _selectedFilter == status;
          return FilterChip(
            label: Text(label),
            selected: isSelected,
            onSelected: (_) => setState(() => _selectedFilter = status),
            selectedColor: AppColors.primaryGreen,
            labelStyle: TextStyle(
              color: isSelected ? Colors.white : AppColors.textPrimary,
              fontWeight: FontWeight.w500,
              fontSize: 12,
            ),
            checkmarkColor: Colors.white,
            side: BorderSide.none,
          );
        },
      ),
    );
  }

  Widget _buildOrderList() {
    return Consumer<OrderProvider>(
      builder: (context, provider, _) {
        if (provider.isLoading) {
          return const LoadingView(message: 'Loading orders...');
        }

        if (provider.error != null) {
          return ErrorView(
            message: provider.error!,
            onRetry: () => provider.loadOrders(),
          );
        }

        final allOrders = provider.orders;
        if (allOrders.isEmpty) {
          return const EmptyView(
            message: 'No orders found',
            title: 'No Orders',
            icon: Icons.shopping_bag_outlined,
          );
        }

        final orders = _selectedFilter == null
            ? allOrders
            : allOrders.where((o) => o.statusEnum == _selectedFilter).toList();

        if (orders.isEmpty) {
          return EmptyView(
            message: 'No ${_selectedFilter!.displayName.toLowerCase()} orders',
            title: 'No ${_selectedFilter!.displayName} Orders',
            icon: Icons.filter_alt_off_outlined,
          );
        }

        return RefreshIndicator(
          onRefresh: () => provider.loadOrders(),
          child: ListView.builder(
            padding: const EdgeInsets.only(bottom: AppConstants.defaultPadding),
            itemCount: orders.length,
            itemBuilder: (context, index) =>
                _buildOrderCard(context, orders[index]),
          ),
        );
      },
    );
  }

  Widget _buildOrderCard(BuildContext context, Order order) {
    return AppCard(
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => OrderDetailScreen(orderId: order.id),
          ),
        );
      },
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.primaryGreen.withAlpha(26),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(
              Icons.shopping_bag_outlined,
              color: AppColors.primaryGreen,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  order.id,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${Formatters.date(order.createdAt)} · ${order.items.length} items',
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
                  fontWeight: FontWeight.w700,
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
  }
}
