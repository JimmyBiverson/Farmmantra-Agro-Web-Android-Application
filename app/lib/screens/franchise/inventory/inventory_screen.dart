import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/enums/app_enums.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/inventory.dart';
import '../../../providers/inventory_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';
import '../../../widgets/common/section_header.dart';

class InventoryScreen extends StatefulWidget {
  const InventoryScreen({super.key});

  @override
  State<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends State<InventoryScreen> {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _buildSummaryCards(),
        const SizedBox(height: AppConstants.smallPadding),
        const SectionHeader(title: 'Inventory Items'),
        Expanded(child: _buildInventoryList()),
      ],
    );
  }

  Widget _buildSummaryCards() {
    return Consumer<InventoryProvider>(
      builder: (context, provider, _) {
        final items = provider.items;
        final totalItems = items.length;
        final totalValue = items.fold<double>(
          0,
          (sum, i) => sum + i.totalValue,
        );
        final lowStockCount = items
            .where(
              (i) =>
                  i.alertLevel == InventoryAlertLevel.low ||
                  i.alertLevel == InventoryAlertLevel.critical,
            )
            .length;

        return Padding(
          padding: const EdgeInsets.fromLTRB(
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            0,
          ),
          child: Row(
            children: [
              Expanded(
                child: _buildMiniStat(
                  'Total Items',
                  '$totalItems',
                  Icons.inventory_2_outlined,
                  AppColors.info,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildMiniStat(
                  'Total Value',
                  Formatters.currency(totalValue),
                  Icons.attach_money,
                  AppColors.primaryGreen,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildMiniStat(
                  'Low Stock',
                  '$lowStockCount',
                  Icons.warning_amber_outlined,
                  lowStockCount > 0 ? AppColors.warning : AppColors.success,
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildMiniStat(
    String label,
    String value,
    IconData icon,
    Color color,
  ) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(AppConstants.smallBorderRadius),
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: color,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(
              fontSize: 10,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInventoryList() {
    return Consumer<InventoryProvider>(
      builder: (context, provider, _) {
        if (provider.isLoading) {
          return const LoadingView(message: 'Loading inventory...');
        }

        if (provider.error != null) {
          return ErrorView(
            message: provider.error!,
            onRetry: () => provider.loadInventory(),
          );
        }

        if (provider.items.isEmpty) {
          return const EmptyView(
            message: 'No inventory items found',
            title: 'No Inventory',
            icon: Icons.inventory_2_outlined,
          );
        }

        return RefreshIndicator(
          onRefresh: () => provider.loadInventory(),
          child: ListView.builder(
            padding: const EdgeInsets.only(bottom: AppConstants.defaultPadding),
            itemCount: provider.items.length,
            itemBuilder: (context, index) =>
                _buildInventoryItemCard(context, provider.items[index]),
          ),
        );
      },
    );
  }

  Widget _buildInventoryItemCard(BuildContext context, InventoryItem item) {
    final alertColor = _getAlertColor(item.alertLevel);

    return AppCard(
      onTap: () => _showMovementHistory(context, item),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 48,
            decoration: BoxDecoration(
              color: alertColor,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.productName,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  item.categoryName,
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
                '${Formatters.quantity(item.quantity)} ${item.unitOfMeasure}',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                  color: alertColor,
                ),
              ),
              const SizedBox(height: 2),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    _getAlertIcon(item.alertLevel),
                    size: 12,
                    color: alertColor,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    'Reorder: ${Formatters.quantity(item.reorderLevel)}',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Color _getAlertColor(InventoryAlertLevel level) {
    switch (level) {
      case InventoryAlertLevel.critical:
      case InventoryAlertLevel.outOfStock:
        return AppColors.error;
      case InventoryAlertLevel.low:
        return AppColors.warning;
      case InventoryAlertLevel.normal:
        return AppColors.success;
    }
  }

  IconData _getAlertIcon(InventoryAlertLevel level) {
    switch (level) {
      case InventoryAlertLevel.critical:
      case InventoryAlertLevel.outOfStock:
        return Icons.error_outline;
      case InventoryAlertLevel.low:
        return Icons.warning_amber_outlined;
      case InventoryAlertLevel.normal:
        return Icons.check_circle_outline;
    }
  }

  void _showMovementHistory(BuildContext context, InventoryItem item) {
    context.read<InventoryProvider>().loadMovements(productId: item.productId);
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) => _MovementHistorySheet(item: item),
    );
  }
}

class _MovementHistorySheet extends StatelessWidget {
  final InventoryItem item;

  const _MovementHistorySheet({required this.item});

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.6,
      minChildSize: 0.3,
      maxChildSize: 0.85,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: const EdgeInsets.all(AppConstants.defaultPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                item.productName,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Movement History · ${Formatters.quantity(item.quantity)} ${item.unitOfMeasure} in stock',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 16),
              Expanded(
                child: Consumer<InventoryProvider>(
                  builder: (context, provider, _) {
                    if (provider.movements.isEmpty) {
                      return const Center(
                        child: Text(
                          'No movement history available',
                          style: TextStyle(color: AppColors.textSecondary),
                        ),
                      );
                    }

                    return ListView.separated(
                      controller: scrollController,
                      itemCount: provider.movements.length,
                      separatorBuilder: (context, index) =>
                          const Divider(height: 1),
                      itemBuilder: (context, index) =>
                          _buildMovementRow(provider.movements[index]),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildMovementRow(InventoryMovement movement) {
    final isInbound = movement.isInbound;
    final color = isInbound ? AppColors.success : AppColors.error;
    final icon = isInbound ? Icons.arrow_downward : Icons.arrow_upward;
    final label = isInbound ? 'Received' : 'Sold';

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: color.withAlpha(26),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 14, color: color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontWeight: FontWeight.w500,
                    fontSize: 13,
                  ),
                ),
                Text(
                  Formatters.dateTime(movement.createdAt),
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.textSecondary,
                  ),
                ),
                if (movement.referenceId != null)
                  Text(
                    'Ref: ${movement.referenceId}',
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
                '${isInbound ? '+' : '-'}${Formatters.quantity(movement.quantity)}',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                  color: color,
                ),
              ),
              Text(
                '${Formatters.quantity(movement.previousQuantity)} → ${Formatters.quantity(movement.newQuantity)}',
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
