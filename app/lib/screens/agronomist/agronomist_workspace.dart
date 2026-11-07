import 'package:flutter/material.dart';
import '../../features/operations/presentation/role_workspace.dart';
import '../../features/operations/presentation/requests_screen.dart';
import '../../features/operations/presentation/field_reports_screen.dart';

RoleWorkspace agronomistWorkspace() => RoleWorkspace(
  'Field Agronomist',
  'Plan your fieldwork. Share knowledge. Keep every visit recorded.',
  [
    WorkspaceAction(
      'Field check-in',
      'Check your current location against an assigned farm',
      Icons.my_location,
      (_) => const FieldZonesScreen(),
    ),
    WorkspaceAction(
      'Weekly travel plan',
      'Plan your route and field activities',
      Icons.route_outlined,
      (_) => const RequestsScreen(initialType: 'travel'),
    ),
    WorkspaceAction(
      'Travel funds',
      'Request funds for approved fieldwork',
      Icons.account_balance_wallet_outlined,
      (_) => const RequestsScreen(initialType: 'funds'),
    ),
    WorkspaceAction(
      'Expenses & visits',
      'Review submissions and visit history',
      Icons.receipt_long_outlined,
      (_) => const RequestsScreen(),
    ),
  ],
);
