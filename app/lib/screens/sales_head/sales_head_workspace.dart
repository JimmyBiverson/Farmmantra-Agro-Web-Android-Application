import 'package:flutter/material.dart';
import '../../features/operations/presentation/role_workspace.dart';
import '../../features/operations/presentation/requests_screen.dart';
import '../../features/operations/presentation/returns_screen.dart';
import '../../features/operations/presentation/reviews_targets_screen.dart';
import '../../features/operations/presentation/field_reports_screen.dart';

RoleWorkspace salesHeadWorkspace() => RoleWorkspace(
  'Sales Head',
  'Turn targets into progress, one franchise at a time.',
  [
    WorkspaceAction(
      'Purchase reviews',
      'Review and forward franchise orders',
      Icons.fact_check_outlined,
      (_) => const OrderReviewsScreen(),
    ),
    WorkspaceAction(
      'Goods return reviews',
      'Review product returns with photographs',
      Icons.assignment_return_outlined,
      (_) => const ReturnsScreen(),
    ),
    WorkspaceAction(
      'Targets & achievements',
      'Weekly, monthly and yearly progress',
      Icons.flag_outlined,
      (_) => const TargetsScreen(),
    ),
    WorkspaceAction(
      'Weekly travel plan',
      'Submit the travel week starting Sunday',
      Icons.route_outlined,
      (_) => const RequestsScreen(initialType: 'travel'),
    ),
    WorkspaceAction(
      'Travel funds',
      'Request and reconcile travel funding',
      Icons.account_balance_wallet_outlined,
      (_) => const RequestsScreen(initialType: 'funds'),
    ),
    WorkspaceAction(
      'Stock & sales reports',
      'Export franchise-wise business records',
      Icons.table_chart_outlined,
      (_) => const ExportReportsScreen(),
    ),
  ],
);
