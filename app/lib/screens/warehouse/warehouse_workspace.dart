import 'package:flutter/material.dart';
import '../../features/operations/presentation/role_workspace.dart';
import '../../features/operations/presentation/requests_screen.dart';
import '../../features/operations/presentation/returns_screen.dart';
import '../staff/inventory/staff_inventory_screen.dart';
import '../staff/orders/staff_order_list_screen.dart';
import '../../features/operations/presentation/workspace_ui.dart';

RoleWorkspace warehouseWorkspace() => RoleWorkspace(
  'Warehouse',
  'A clear path from stock arrival to franchise delivery.',
  [
    WorkspaceAction(
      'Stock IN & OUT',
      'View central warehouse and franchise inventory',
      Icons.warehouse_outlined,
      (_) => const StaffInventoryScreen(),
    ),
    WorkspaceAction(
      'Order dispatch',
      'Review orders and record delivery details',
      Icons.local_shipping_outlined,
      (_) => const WorkspacePage(
        title: 'Warehouse orders',
        child: StaffOrderListScreen(),
      ),
    ),
    WorkspaceAction(
      'Physical return receipt',
      'Check and book approved goods received',
      Icons.assignment_return_outlined,
      (_) => const ReturnsScreen(),
    ),
    WorkspaceAction(
      'Warehouse funds',
      'Request operational funds',
      Icons.account_balance_wallet_outlined,
      (_) => const RequestsScreen(initialType: 'funds'),
    ),
  ],
);
