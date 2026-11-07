import 'features/operations/presentation/operations_home.dart';
import 'features/operations/presentation/returns_screen.dart';
import 'features/operations/presentation/requests_screen.dart';
import 'screens/admin/admin_shell.dart';
import 'screens/sales_head/sales_head_shell.dart';
import 'screens/agronomist/agronomist_shell.dart';
import 'screens/warehouse/warehouse_shell.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'core/theme/app_theme.dart';
import 'core/enums/user_role.dart';
import 'providers/auth_provider.dart';
import 'screens/splash/splash_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/franchise/franchise_shell.dart';
import 'screens/franchise/payments/payment_screen.dart';
import 'screens/staff/staff_shell.dart';
import 'screens/finance/finance_shell.dart';
import 'screens/finance/payments/payment_detail_screen.dart';
import 'screens/franchise/orders/order_detail_screen.dart';
import 'screens/staff/orders/staff_order_detail_screen.dart';
import 'screens/shared/terms/terms_screen.dart';
import 'screens/franchise/statement/statement_screen.dart';
import 'services/notification_service.dart';

class FarmmantraApp extends StatefulWidget {
  const FarmmantraApp({super.key});
  @override
  State<FarmmantraApp> createState() => _FarmmantraAppState();
}

class _FarmmantraAppState extends State<FarmmantraApp> {
  GoRouter? _router;

  String _home(AuthProvider auth) => switch (auth.userRole) {
    UserRole.franchisePartner => '/franchise/dashboard',
    UserRole.financeDepartment => '/finance/dashboard',
    UserRole.systemAdministrator => '/admin/dashboard',
    UserRole.salesHead => '/sales-head/dashboard',
    UserRole.fieldAgronomist => '/agronomist/dashboard',
    UserRole.warehouse => '/warehouse/dashboard',
    _ => '/staff/dashboard',
  };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final auth = context.read<AuthProvider>();
    _router ??= GoRouter(
      navigatorKey: NotificationService.navigatorKey,
      refreshListenable: auth,
      initialLocation: '/',
      redirect: (context, state) {
        final path = state.uri.path;
        if (!auth.isInitialized && !auth.isAuthenticated) {
          return path == '/' ? null : '/';
        }
        if (!auth.isAuthenticated) return path == '/login' ? null : '/login';
        if (auth.user?.needsTermsAcceptance == true) {
          return path == '/terms' ? null : '/terms';
        }
        if (path == '/' || path == '/login' || path == '/terms') {
          return _home(auth);
        }
        final authorized = path.startsWith('/operations')
            ? true
            : path.startsWith('/admin/')
            ? auth.userRole == UserRole.systemAdministrator
            : path.startsWith('/sales-head/')
            ? auth.userRole == UserRole.salesHead
            : path.startsWith('/agronomist/')
            ? auth.userRole == UserRole.fieldAgronomist
            : path.startsWith('/warehouse/')
            ? auth.userRole == UserRole.warehouse
            : path.startsWith('/franchise/')
            ? auth.userRole == UserRole.franchisePartner
            : path.startsWith('/finance/')
            ? auth.userRole == UserRole.financeDepartment
            : [
                UserRole.systemAdministrator,
                UserRole.farmmantraStaff,
                UserRole.warehouse,
              ].contains(auth.userRole);
        return authorized ? null : _home(auth);
      },
      routes: [
        GoRoute(
          path: '/operations',
          builder: (_, state) => const OperationsHubScreen(),
        ),
        GoRoute(
          path: '/operations/returns',
          builder: (_, state) => const ReturnsScreen(),
        ),
        GoRoute(
          path: '/operations/requests',
          builder: (_, state) => const RequestsScreen(),
        ),
        GoRoute(
          path: '/admin/dashboard',
          builder: (_, state) => const AdminShell(),
        ),
        GoRoute(
          path: '/sales-head/dashboard',
          builder: (_, state) => const SalesHeadShell(),
        ),
        GoRoute(
          path: '/agronomist/dashboard',
          builder: (_, state) => const AgronomistShell(),
        ),
        GoRoute(
          path: '/warehouse/dashboard',
          builder: (_, state) => const WarehouseShell(),
        ),
        GoRoute(path: '/', builder: (_, state) => const SplashScreen()),
        GoRoute(path: '/login', builder: (_, state) => const LoginScreen()),
        GoRoute(path: '/terms', builder: (_, state) => const TermsScreen()),
        GoRoute(
          path: '/franchise/dashboard',
          builder: (_, state) => const FranchiseShell(),
        ),
        GoRoute(
          path: '/staff/dashboard',
          builder: (_, state) => const StaffShell(),
        ),
        GoRoute(
          path: '/finance/dashboard',
          builder: (_, state) => const FinanceShell(),
        ),
        GoRoute(
          path: '/finance/payment-detail',
          builder: (_, state) =>
              FinancePaymentDetailScreen(paymentId: _id(state)),
        ),
        GoRoute(
          path: '/franchise/order-detail',
          builder: (_, state) => OrderDetailScreen(orderId: _id(state)),
        ),
        GoRoute(
          path: '/franchise/statement',
          builder: (_, state) => const StatementScreen(),
        ),
        GoRoute(
          path: '/franchise/payment',
          builder: (_, state) => const PaymentScreen(),
        ),
        GoRoute(
          path: '/staff/order-detail',
          builder: (_, state) => StaffOrderDetailScreen(orderId: _id(state)),
        ),
        GoRoute(
          path: '/staff/orders/detail',
          redirect: (_, state) => '/staff/order-detail?id=${_id(state)}',
        ),
      ],
      errorBuilder: (_, state) => const Scaffold(
        body: Center(child: Text('This page could not be found.')),
      ),
    );
  }

  String _id(GoRouterState state) =>
      state.extra as String? ?? state.uri.queryParameters['id'] ?? '';

  @override
  Widget build(BuildContext context) => MaterialApp.router(
    title: 'Farmmantra',
    debugShowCheckedModeBanner: false,
    theme: AppTheme.lightTheme,
    routerConfig: _router!,
  );

  @override
  void dispose() {
    _router?.dispose();
    super.dispose();
  }
}
