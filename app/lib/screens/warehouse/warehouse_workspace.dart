import 'package:flutter/material.dart';
import '../../features/operations/presentation/role_workspace.dart';
import '../../features/operations/presentation/requests_screen.dart';
import '../../features/operations/presentation/returns_screen.dart';
import '../staff/staff_shell.dart';

RoleWorkspace warehouseWorkspace() => RoleWorkspace(
  'Warehouse',
  'A clear path from stock arrival to franchise delivery.',
  [
    WorkspaceAction(
      'Stock IN & OUT',
      'Open the warehouse orders and stock workspace',
      Icons.warehouse_outlined,
      (_) => const StaffShell(),
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
