import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:farmmantra/providers/order_provider.dart';
import 'package:farmmantra/screens/franchise/orders/order_list_screen.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';

void main() {
  late MockApiService apiService;
  late OrderProvider provider;

  setUp(() async {
    apiService = MockApiService();
    await apiService.initialize();
    provider = OrderProvider(apiService: apiService);
    await provider.loadOrders();
  });

  Widget buildScreen() {
    return ChangeNotifierProvider<OrderProvider>.value(
      value: provider,
      child: const MaterialApp(home: Scaffold(body: OrderListScreen())),
    );
  }

  Finder chip(String label) => find.widgetWithText(FilterChip, label);

  testWidgets('shows all orders by default', (tester) async {
    await tester.pumpWidget(buildScreen());
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 1200));
    await tester.pumpAndSettle();

    expect(find.text('ORD-1001'), findsOneWidget);
    expect(find.text('ORD-1002'), findsOneWidget);
    expect(find.text('ORD-1003'), findsOneWidget);
  });

  testWidgets('filtering by Approved shows only approved orders', (
    tester,
  ) async {
    await tester.pumpWidget(buildScreen());
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 1200));
    await tester.pumpAndSettle();

    await tester.tap(chip('Approved'));
    await tester.pumpAndSettle();

    expect(find.text('ORD-1002'), findsOneWidget);
    expect(find.text('ORD-1001'), findsNothing);
    expect(find.text('ORD-1003'), findsNothing);
  });

  testWidgets('filtering by Delivered shows only delivered orders', (
    tester,
  ) async {
    await tester.pumpWidget(buildScreen());
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 1200));
    await tester.pumpAndSettle();

    await tester.tap(chip('Delivered'));
    await tester.pumpAndSettle();

    expect(find.text('ORD-1003'), findsOneWidget);
    expect(find.text('ORD-1001'), findsNothing);
    expect(find.text('ORD-1002'), findsNothing);
  });

  testWidgets('filtering by Pending shows only pending orders', (tester) async {
    await tester.pumpWidget(buildScreen());
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 1200));
    await tester.pumpAndSettle();

    await tester.tap(chip('Pending'));
    await tester.pumpAndSettle();

    expect(find.text('ORD-1001'), findsOneWidget);
    expect(find.text('ORD-1002'), findsNothing);
    expect(find.text('ORD-1003'), findsNothing);
  });

  testWidgets('switching back to All restores the full list', (tester) async {
    await tester.pumpWidget(buildScreen());
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 1200));
    await tester.pumpAndSettle();

    await tester.tap(chip('Approved'));
    await tester.pumpAndSettle();
    expect(find.text('ORD-1001'), findsNothing);

    await tester.tap(chip('All'));
    await tester.pumpAndSettle();

    expect(find.text('ORD-1001'), findsOneWidget);
    expect(find.text('ORD-1002'), findsOneWidget);
    expect(find.text('ORD-1003'), findsOneWidget);
  });

  testWidgets('empty state shows a message when no orders match the filter', (
    tester,
  ) async {
    await tester.pumpWidget(buildScreen());
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 1200));
    await tester.pumpAndSettle();

    await tester.ensureVisible(chip('Cancelled'));
    await tester.pumpAndSettle();
    await tester.tap(chip('Cancelled'));
    await tester.pumpAndSettle();

    expect(find.text('No Cancelled Orders'), findsOneWidget);
    expect(find.text('ORD-1001'), findsNothing);
  });
}
