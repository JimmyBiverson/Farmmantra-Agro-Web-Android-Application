import 'admin_data_screen.dart';
import 'admin_branding_screen.dart';
import 'package:flutter/material.dart';
import '../../features/operations/presentation/role_workspace.dart';
import '../../features/operations/presentation/requests_screen.dart';
import '../../features/operations/presentation/returns_screen.dart';
import '../../features/operations/presentation/reviews_targets_screen.dart';
import '../../features/operations/presentation/field_reports_screen.dart';
import '../../features/operations/presentation/knowledge_screen.dart';

RoleWorkspace adminWorkspace() => RoleWorkspace(
  'Admin workspace',
  'Lead the operation with a clear view of every approval.',
  [
    WorkspaceAction(
      'Brand identity',
      'Manage site logo, favicon and app icon',
      Icons.palette_outlined,
      (_) => const AdminBrandingScreen(),
    ),
    WorkspaceAction(
      'Products & prices',
      'Manage actual photos, batches, expiry and price tiers',
      Icons.inventory_2_outlined,
      (_) => const AdminDataScreen(entity: 'products'),
    ),
    WorkspaceAction(
      'Create franchises',
      'Manage franchise accounts and credit limits',
      Icons.store_outlined,
      (_) => const AdminDataScreen(entity: 'franchises'),
    ),
    WorkspaceAction(
      'Create retailers & farmers',
      'Assign customers to a franchise and district',
      Icons.people_outline,
      (_) => const AdminDataScreen(entity: 'customers'),
    ),
    WorkspaceAction(
      'User accounts',
      'Create role-specific user access',
      Icons.manage_accounts_outlined,
      (_) => const AdminDataScreen(entity: 'users'),
    ),
    WorkspaceAction(
      'Order approvals',
      'Review purchases after Sales Head approval',
      Icons.fact_check_outlined,
      (_) => const OrderReviewsScreen(),
    ),
    WorkspaceAction(
      'Funds & travel approvals',
      'Approve funding and weekly travel plans',
      Icons.approval_outlined,
      (_) => const RequestsScreen(),
    ),
    WorkspaceAction(
      'Goods returns',
      'Review returns before warehouse receipt',
      Icons.assignment_return_outlined,
      (_) => const ReturnsScreen(),
    ),
    WorkspaceAction(
      'Targets & achievements',
      'Set franchise and Sales Head milestones',
      Icons.flag_outlined,
      (_) => const TargetsScreen(),
    ),
    WorkspaceAction(
      'Knowledge publishing',
      'Prepare and publish verified crop guidance',
      Icons.menu_book_outlined,
      (_) => const KnowledgeScreen(),
    ),
    WorkspaceAction(
      'Field boundaries',
      'Configure client-approved geofences',
      Icons.add_location_alt_outlined,
      (_) => const FieldZonesScreen(),
    ),
    WorkspaceAction(
      'Budgets & expenses',
      'Compare monthly budget and actual spending',
      Icons.account_balance_wallet_outlined,
      (_) => const RequestsScreen(initialType: 'budget'),
    ),
    WorkspaceAction(
      'Excel reports',
      'Export stock, sales, expenses and outstanding',
      Icons.table_chart_outlined,
      (_) => const ExportReportsScreen(),
    ),
  ],
);
