import '../../features/operations/presentation/workspace_ui.dart';
import 'package:flutter/material.dart';
import '../../features/operations/presentation/role_workspace.dart';
import '../../features/operations/presentation/requests_screen.dart';
import '../../features/operations/presentation/field_reports_screen.dart';
import 'payments/payment_list_screen.dart';

RoleWorkspace financeWorkspace() => RoleWorkspace(
  'Finance operations',
  'From verified receipts to accountable spending.',
  [
    WorkspaceAction(
      'Pending payment approvals',
      'Check bank receipts and approve payments',
      Icons.fact_check_outlined,
      (_) => const WorkspacePage(
        title: 'Pending payments',
        child: PaymentListScreen(),
      ),
    ),
    WorkspaceAction(
      'Expense approvals & funds',
      'Review expenses and process Admin-approved funds',
      Icons.account_balance_wallet_outlined,
      (_) => const RequestsScreen(),
    ),
    WorkspaceAction(
      'Excel reports',
      'Export expenses, outstanding and business records',
      Icons.table_chart_outlined,
      (_) => const ExportReportsScreen(),
    ),
  ],
);
