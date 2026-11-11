import '../../features/operations/presentation/workspace_ui.dart';
import 'package:flutter/material.dart';
import '../../features/operations/presentation/role_workspace.dart';
import '../../features/operations/presentation/requests_screen.dart';
import '../../features/operations/presentation/returns_screen.dart';
import '../../features/operations/presentation/reviews_targets_screen.dart';
import '../../features/operations/presentation/field_reports_screen.dart';
import 'sales/create_sale_screen.dart';
import 'orders/create_order_screen.dart';
import 'customers/customer_list_screen.dart';
import 'inventory/inventory_screen.dart';
import 'payments/payment_screen.dart';

RoleWorkspace franchiseWorkspace() => RoleWorkspace(
  'Franchise operations',
  'Your stock, sales and everyday operations, connected.',
  [
    WorkspaceAction(
      'Post daily expense',
      'Submit expenses and reconcile received funds',
      Icons.receipt_long_outlined,
      (_) => const RequestFormScreen(initialType: 'expense'),
    ),
    WorkspaceAction(
      'Stock IN · new order',
      'Place an order for franchise stock',
      Icons.add_box_outlined,
      (_) => const CreateOrderScreen(),
    ),
    WorkspaceAction(
      'Stock OUT · record sale',
      'Sell to a registered retailer or farmer',
      Icons.point_of_sale_outlined,
      (_) => const CreateSaleScreen(),
    ),
    WorkspaceAction(
      'Goods returns',
      'Request a return against received orders',
      Icons.assignment_return_outlined,
      (_) => const ReturnsScreen(),
    ),
    WorkspaceAction(
      'Operations funds',
      'Request funds and track reviews',
      Icons.account_balance_wallet_outlined,
      (_) => const RequestsScreen(initialType: 'funds'),
    ),
    WorkspaceAction(
      'Sales achievements',
      'Weekly, monthly and yearly targets',
      Icons.flag_outlined,
      (_) => const TargetsScreen(),
    ),
    WorkspaceAction(
      'Farmers & retailers',
      'Register customers and capture sales details',
      Icons.people_outline,
      (_) => const WorkspacePage(
        title: 'Farmers & retailers',
        child: CustomerListScreen(),
      ),
    ),
    WorkspaceAction(
      'Stock report',
      'Your current stock and low-stock alerts',
      Icons.inventory_2_outlined,
      (_) =>
          const WorkspacePage(title: 'Stock report', child: InventoryScreen()),
    ),
    WorkspaceAction(
      'Download stock report',
      'Save your current stock workbook for the weekly review',
      Icons.download_outlined,
      (_) => const ExportReportsScreen(franchiseOnly: true),
    ),
    WorkspaceAction(
      'Post a payment',
      'Submit payments for finance verification',
      Icons.payments_outlined,
      (_) => const PaymentScreen(),
    ),
    WorkspaceAction(
      'All expense submissions',
      'Review your expense and funding history',
      Icons.history,
      (_) => const RequestsScreen(),
    ),
  ],
);
