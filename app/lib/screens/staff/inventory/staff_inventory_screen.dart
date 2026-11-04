import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/enums/app_enums.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/inventory.dart';
import '../../../providers/inventory_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';

class StaffInventoryScreen extends StatefulWidget {
  const StaffInventoryScreen({super.key});

  @override
  State<StaffInventoryScreen> createState() => _StaffInventoryScreenState();
}

class _StaffInventoryScreenState extends State<StaffInventoryScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  String? _selectedFranchiseId;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<InventoryProvider>();
      provider.loadInventory(silent: provider.items.isNotEmpty);
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventory'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          indicatorWeight: 3,
          tabs: const [
            Tab(text: 'Warehouse'),
            Tab(text: 'Franchise Stock'),
          ],
        ),
      ),
      body: Consumer<InventoryProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading) {
            return const LoadingView();
          }

          if (provider.error != null) {
            return ErrorView(
              message: provider.error!,
              onRetry: () => provider.loadInventory(),
            );
          }

          return TabBarView(
            controller: _tabController,
            children: [
              _buildWarehouseTab(provider),
              _buildFranchiseTab(provider),
            ],
          );
        },
      ),
    );
  }

  List<InventoryItem> _getWarehouseItems(InventoryProvider provider) {
    return provider.items.where((item) => item.franchiseId == null).toList();
  }

  List<String> _getFranchiseIds(InventoryProvider provider) {
    return provider.items
        .where((item) => item.franchiseId != null)
        .map((item) => item.franchiseId!)
        .toSet()
        .toList();
  }

  List<InventoryItem> _getFranchiseItems(
    InventoryProvider provider,
    String? franchiseId,
  ) {
    final allFranchiseItems = provider.items
        .where((item) => item.franchiseId != null)
        .toList();
    if (franchiseId == null || franchiseId == 'ALL') {
      return allFranchiseItems;
    }
    return allFranchiseItems
        .where((item) => item.franchiseId == franchiseId)
        .toList();
  }

  Widget _buildWarehouseTab(InventoryProvider provider) {
    final warehouseItems = _getWarehouseItems(provider);
    final lowStockCount = warehouseItems
        .where((item) => item.alertLevel == InventoryAlertLevel.low)
        .length;
    final outOfStockCount = warehouseItems
        .where((item) => item.alertLevel == InventoryAlertLevel.outOfStock)
        .length;
    final totalValue = warehouseItems.fold<double>(
      0,
      (sum, item) => sum + item.totalValue,
    );

    return RefreshIndicator(
      onRefresh: () => provider.loadInventory(),
      child: Column(
        children: [
          _buildSummaryCards(
            totalValue: totalValue,
            lowStockCount: lowStockCount,
            outOfStockCount: outOfStockCount,
          ),
          Expanded(
            child: warehouseItems.isEmpty
                ? const EmptyView(
                    message: 'No warehouse items found',
                    icon: Icons.warehouse_outlined,
                  )
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: warehouseItems.length,
                    itemBuilder: (context, index) {
                      return _buildInventoryItem(warehouseItems[index]);
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildFranchiseTab(InventoryProvider provider) {
    final franchiseIds = _getFranchiseIds(provider);
    final activeFranchiseId = _selectedFranchiseId ?? 'ALL';
    final franchiseItems = _getFranchiseItems(provider, activeFranchiseId);

    final lowStockCount = franchiseItems
        .where((item) => item.alertLevel == InventoryAlertLevel.low)
        .length;
    final outOfStockCount = franchiseItems
        .where((item) => item.alertLevel == InventoryAlertLevel.outOfStock)
        .length;
    final totalValue = franchiseItems.fold<double>(
      0,
      (sum, item) => sum + item.totalValue,
    );

    return RefreshIndicator(
      onRefresh: () => provider.loadInventory(),
      child: Column(
        children: [
          _buildSummaryCards(
            totalValue: totalValue,
            lowStockCount: lowStockCount,
            outOfStockCount: outOfStockCount,
          ),
          if (franchiseIds.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: DropdownButtonFormField<String>(
                initialValue: activeFranchiseId,
                decoration: const InputDecoration(
                  labelText: 'Filter Franchise',
                  border: OutlineInputBorder(),
                  contentPadding: EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 10,
                  ),
                ),
                items: [
                  const DropdownMenuItem(
                    value: 'ALL',
                    child: Text('All Franchises Stock'),
                  ),
                  ...franchiseIds.map((franchiseId) {
                    final item = provider.items.firstWhere(
                      (i) => i.franchiseId == franchiseId,
                    );
                    return DropdownMenuItem(
                      value: franchiseId,
                      child: Text(
                        item.franchiseName ?? 'Franchise #$franchiseId',
                      ),
                    );
                  }),
                ],
                onChanged: (value) {
                  setState(() => _selectedFranchiseId = value);
                },
              ),
            ),
          Expanded(
            child: franchiseItems.isEmpty
                ? const EmptyView(
                    message: 'No franchise stock items found',
                    icon: Icons.store_outlined,
                  )
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: franchiseItems.length,
                    itemBuilder: (context, index) {
                      final item = franchiseItems[index];
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (item.franchiseName != null &&
                              activeFranchiseId == 'ALL')
                            Padding(
                              padding: const EdgeInsets.only(
                                top: 4,
                                bottom: 2,
                                left: 4,
                              ),
                              child: Text(
                                item.franchiseName!,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.primaryGreen,
                                ),
                              ),
                            ),
                          _buildInventoryItem(item),
                        ],
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryCards({
    required double totalValue,
    required int lowStockCount,
    required int outOfStockCount,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: StatCard(
              title: 'Total Value',
              value: Formatters.currency(totalValue),
              icon: Icons.attach_money,
              iconColor: AppColors.primaryGreen,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: StatCard(
              title: 'Low Stock',
              value: '$lowStockCount',
              icon: Icons.warning_amber,
              iconColor: AppColors.warning,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: StatCard(
              title: 'Out of Stock',
              value: '$outOfStockCount',
              icon: Icons.error_outline,
              iconColor: AppColors.error,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInventoryItem(InventoryItem item) {
    final alertColor = _getAlertColor(item.alertLevel);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: AppCard(
        color: alertColor.withValues(alpha: 0.03),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      item.productName,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  _buildAlertBadge(item.alertLevel),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _buildStockInfo(
                    'Current Stock',
                    '${item.quantity} ${item.unitOfMeasure}',
                    alertColor,
                  ),
                  _buildStockInfo(
                    'Reorder Level',
                    '${item.reorderLevel} ${item.unitOfMeasure}',
                    AppColors.textSecondary,
                  ),
                  _buildStockInfo(
                    'Unit Cost',
                    Formatters.currency(item.unitCost),
                    AppColors.textSecondary,
                  ),
                ],
              ),
              const SizedBox(height: 8),
              if (item.isLowStock)
                LinearProgressIndicator(
                  value: item.reorderLevel > 0
                      ? (item.quantity / item.reorderLevel).clamp(0.0, 1.0)
                      : 0,
                  backgroundColor: AppColors.divider,
                  valueColor: AlwaysStoppedAnimation<Color>(alertColor),
                  minHeight: 6,
                  borderRadius: BorderRadius.circular(3),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildAlertBadge(InventoryAlertLevel level) {
    Color color;
    String label;
    IconData icon;

    switch (level) {
      case InventoryAlertLevel.critical:
        color = AppColors.error;
        label = 'Critical';
        icon = Icons.error;
        break;
      case InventoryAlertLevel.low:
        color = AppColors.warning;
        label = 'Low';
        icon = Icons.warning_amber;
        break;
      case InventoryAlertLevel.outOfStock:
        color = AppColors.error;
        label = 'Out of Stock';
        icon = Icons.block;
        break;
      case InventoryAlertLevel.normal:
        color = AppColors.success;
        label = 'Normal';
        icon = Icons.check_circle;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 4),
          Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: color,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStockInfo(String label, String value, Color color) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(
            context,
          ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            fontWeight: FontWeight.w500,
            color: color,
          ),
        ),
      ],
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
}
