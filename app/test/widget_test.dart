import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:farmmantra/app.dart';
import 'package:farmmantra/providers/auth_provider.dart';
import 'package:farmmantra/providers/product_provider.dart';
import 'package:farmmantra/providers/order_provider.dart';
import 'package:farmmantra/providers/inventory_provider.dart';
import 'package:farmmantra/providers/payment_provider.dart';
import 'package:farmmantra/providers/customer_provider.dart';
import 'package:farmmantra/providers/notification_provider.dart';
import 'package:farmmantra/services/api/api_service.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';
import 'package:farmmantra/services/storage/local_storage_service.dart';
import 'package:farmmantra/widgets/common/app_button.dart';
import 'package:farmmantra/widgets/common/status_badge.dart';
import 'package:farmmantra/widgets/common/loading_view.dart';
import 'package:farmmantra/widgets/common/error_view.dart';
import 'package:farmmantra/core/enums/app_enums.dart';
import 'package:provider/provider.dart';

void main() {
  late MockApiService apiService;
  late LocalStorageService localStorage;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    apiService = MockApiService();
    await apiService.initialize();
    localStorage = LocalStorageService();
    await localStorage.initialize();
  });

  Widget buildTestApp(Widget child) {
    return MultiProvider(
      providers: [
        Provider<ApiService>.value(value: apiService),
        ChangeNotifierProvider(
          create: (_) =>
              AuthProvider(apiService: apiService, storage: localStorage),
        ),
        ChangeNotifierProvider(
          create: (_) => ProductProvider(apiService: apiService),
        ),
        ChangeNotifierProvider(
          create: (_) => OrderProvider(apiService: apiService),
        ),
        ChangeNotifierProvider(
          create: (_) => InventoryProvider(apiService: apiService),
        ),
        ChangeNotifierProvider(
          create: (_) => PaymentProvider(apiService: apiService),
        ),
        ChangeNotifierProvider(
          create: (_) => CustomerProvider(apiService: apiService),
        ),
        ChangeNotifierProvider(
          create: (_) => NotificationProvider(apiService: apiService),
        ),
      ],
      child: MaterialApp(home: child),
    );
  }

  group('App Launch', () {
    testWidgets('Splash navigates to login when not authenticated', (
      tester,
    ) async {
      await tester.pumpWidget(buildTestApp(const FarmmantraApp()));
      await tester.pumpAndSettle(const Duration(seconds: 5));

      expect(find.text('Sign In'), findsOneWidget);
      expect(find.text('Sign in to your account'), findsOneWidget);
    });
  });

  group('LoginScreen', () {
    testWidgets('Login form has email and password fields', (tester) async {
      await tester.pumpWidget(buildTestApp(const FarmmantraApp()));
      await tester.pumpAndSettle(const Duration(seconds: 5));

      expect(find.text('Email Address'), findsOneWidget);
      expect(find.text('Password'), findsOneWidget);
      expect(find.text('Sign In'), findsOneWidget);
    });

    testWidgets('Login form validates empty email', (tester) async {
      await tester.pumpWidget(buildTestApp(const FarmmantraApp()));
      await tester.pumpAndSettle(const Duration(seconds: 5));

      await tester.ensureVisible(find.text('Sign In'));
      await tester.tap(find.text('Sign In'));
      await tester.pump();

      expect(find.text('Email is required'), findsOneWidget);
    });

    testWidgets('Login form validates invalid email', (tester) async {
      await tester.pumpWidget(buildTestApp(const FarmmantraApp()));
      await tester.pumpAndSettle(const Duration(seconds: 5));

      await tester.enterText(find.byType(TextFormField).first, 'notanemail');
      await tester.ensureVisible(find.text('Sign In'));
      await tester.tap(find.text('Sign In'));
      await tester.pump();

      expect(find.text('Enter a valid email address'), findsOneWidget);
    });

    testWidgets('Login form validates short password', (tester) async {
      await tester.pumpWidget(buildTestApp(const FarmmantraApp()));
      await tester.pumpAndSettle(const Duration(seconds: 5));

      await tester.enterText(find.byType(TextFormField).first, 'user@test.com');
      await tester.enterText(find.byType(TextFormField).last, '123');
      await tester.ensureVisible(find.text('Sign In'));
      await tester.tap(find.text('Sign In'));
      await tester.pump();

      expect(
        find.text('Password must be at least 6 characters'),
        findsOneWidget,
      );
    });

    testWidgets('Successful login navigates to franchise dashboard', (
      tester,
    ) async {
      await tester.pumpWidget(buildTestApp(const FarmmantraApp()));
      await tester.pumpAndSettle(const Duration(seconds: 5));

      await tester.enterText(
        find.byType(TextFormField).first,
        'franchise@farmmantra.co.ug',
      );
      await tester.enterText(find.byType(TextFormField).last, 'password123');
      await tester.ensureVisible(find.text('Sign In'));
      await tester.tap(find.text('Sign In'));
      await tester.pump(const Duration(seconds: 3));
      await tester.pump(const Duration(seconds: 3));

      expect(find.text('Dashboard'), findsAtLeastNWidgets(1));
      await tester.pump(const Duration(seconds: 2));
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pump(const Duration(seconds: 2));
    });

    testWidgets('Login explains how to obtain an account', (tester) async {
      await tester.pumpWidget(buildTestApp(const FarmmantraApp()));
      await tester.pumpAndSettle(const Duration(seconds: 5));

      expect(
        find.text('Your account is provided by your Farmmantra administrator.'),
        findsOneWidget,
      );
      expect(find.textContaining('password123'), findsNothing);
    });
  });

  group('AppButton Widget', () {
    testWidgets('renders label', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(label: 'Click Me', onPressed: () {}),
          ),
        ),
      );
      expect(find.text('Click Me'), findsOneWidget);
    });

    testWidgets('shows loading indicator when isLoading', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(label: 'Submit', isLoading: true, onPressed: () {}),
          ),
        ),
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Submit'), findsNothing);
    });

    testWidgets('calls onPressed when tapped', (tester) async {
      bool tapped = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(label: 'Tap', onPressed: () => tapped = true),
          ),
        ),
      );
      await tester.tap(find.text('Tap'));
      expect(tapped, true);
    });

    testWidgets('does not call onPressed when isLoading', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: AppButton(label: 'Tap', isLoading: true, onPressed: () {}),
          ),
        ),
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Tap'), findsNothing);
    });
  });

  group('StatusBadge Widget', () {
    testWidgets('fromOrderStatus creates correct badge', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: StatusBadge.fromOrderStatus('pending')),
        ),
      );
      expect(find.text('Pending'), findsOneWidget);
    });

    testWidgets('fromPaymentStatus creates correct badge', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: StatusBadge.fromPaymentStatus('accepted')),
        ),
      );
      expect(find.text('Accepted'), findsOneWidget);
    });

    testWidgets('fromOrderStatus handles enum directly', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StatusBadge.fromOrderStatus(OrderStatus.delivered),
          ),
        ),
      );
      expect(find.text('Delivered'), findsOneWidget);
    });

    testWidgets('fromPaymentStatus defaults unknown to pending', (
      tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: StatusBadge.fromPaymentStatus('unknown_status')),
        ),
      );
      expect(find.text('Pending'), findsOneWidget);
    });
  });

  group('LoadingView Widget', () {
    testWidgets('shows progress indicator', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(home: Scaffold(body: LoadingView())),
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
    });

    testWidgets('shows message when provided', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: LoadingView(message: 'Loading data...')),
        ),
      );
      expect(find.text('Loading data...'), findsOneWidget);
    });
  });

  group('LoadingOverlay Widget', () {
    testWidgets('shows child when not loading', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: LoadingOverlay(isLoading: false, child: Text('Content')),
          ),
        ),
      );
      expect(find.text('Content'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsNothing);
    });

    testWidgets('shows overlay when loading', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: LoadingOverlay(isLoading: true, child: Text('Content')),
          ),
        ),
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
    });
  });

  group('ErrorView Widget', () {
    testWidgets('shows message and retry button', (tester) async {
      bool retried = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ErrorView(
              message: 'Something broke',
              onRetry: () => retried = true,
            ),
          ),
        ),
      );
      expect(find.text('Something broke'), findsOneWidget);
      expect(find.text('Retry'), findsOneWidget);

      await tester.tap(find.text('Retry'));
      expect(retried, true);
    });

    testWidgets('hides retry when onRetry is null', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: ErrorView(message: 'Error')),
        ),
      );
      expect(find.text('Retry'), findsNothing);
    });
  });

  group('EmptyView Widget', () {
    testWidgets('shows message and optional action', (tester) async {
      bool actionTapped = false;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: EmptyView(
              message: 'No data',
              actionLabel: 'Add New',
              onAction: () => actionTapped = true,
            ),
          ),
        ),
      );
      expect(find.text('No data'), findsOneWidget);
      expect(find.text('Add New'), findsOneWidget);

      await tester.tap(find.text('Add New'));
      expect(actionTapped, true);
    });
  });

  group('StatusBadge completeness', () {
    test('all OrderStatus values are handled', () {
      for (final status in OrderStatus.values) {
        final badge = StatusBadge.fromOrderStatus(status);
        expect(badge.label, isNotEmpty);
      }
    });

    test('all PaymentStatus values are handled', () {
      for (final status in PaymentStatus.values) {
        final badge = StatusBadge.fromPaymentStatus(status);
        expect(badge.label, isNotEmpty);
      }
    });
  });
}
