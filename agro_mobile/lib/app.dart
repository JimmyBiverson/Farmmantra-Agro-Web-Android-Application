import 'package:flutter/material.dart';
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

class FarmmantraApp extends StatelessWidget {
  const FarmmantraApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Farmmantra',
      navigatorKey: NotificationService.navigatorKey,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      initialRoute: '/',
      onGenerateRoute: (settings) {
        switch (settings.name) {
          case '/':
            return MaterialPageRoute(builder: (_) => const SplashScreen());
          case '/login':
            return MaterialPageRoute(builder: (_) => const LoginScreen());
          case '/franchise/dashboard':
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                const FranchiseShell(),
                allowedRoles: [UserRole.franchisePartner],
              ),
            );
          case '/staff/dashboard':
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                const StaffShell(),
                allowedRoles: const [
                  UserRole.farmmantraStaff,
                  UserRole.warehouse,
                  UserRole.systemAdministrator,
                ],
              ),
            );
          case '/finance/dashboard':
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                const FinanceShell(),
                allowedRoles: [UserRole.financeDepartment],
              ),
            );
          case '/finance/payment-detail':
            final paymentId = settings.arguments as String?;
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                FinancePaymentDetailScreen(paymentId: paymentId ?? ''),
                allowedRoles: [UserRole.financeDepartment],
              ),
            );
          case '/franchise/order-detail':
            final orderId = settings.arguments as String?;
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                OrderDetailScreen(orderId: orderId ?? ''),
                allowedRoles: [UserRole.franchisePartner],
              ),
            );
          case '/terms':
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                const TermsScreen(),
                allowedRoles: [UserRole.franchisePartner],
              ),
            );
          case '/franchise/statement':
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                const StatementScreen(),
                allowedRoles: [UserRole.franchisePartner],
              ),
            );
          case '/franchise/payment':
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                const PaymentScreen(),
                allowedRoles: [UserRole.franchisePartner],
              ),
            );
          case '/staff/order-detail':
          case '/staff/orders/detail':
            final orderId = settings.arguments as String?;
            return MaterialPageRoute(
              builder: (_) => _requireAuth(
                context,
                StaffOrderDetailScreen(orderId: orderId ?? ''),
                allowedRoles: const [
                  UserRole.farmmantraStaff,
                  UserRole.warehouse,
                  UserRole.systemAdministrator,
                ],
              ),
            );
          default:
            return MaterialPageRoute(builder: (_) => const LoginScreen());
        }
      },
    );
  }

  Widget _requireAuth(
    BuildContext context,
    Widget child, {
    List<UserRole>? allowedRoles,
  }) {
    final auth = context.read<AuthProvider>();

    if (!auth.isAuthenticated) {
      return const LoginScreen();
    }

    if (allowedRoles != null && !allowedRoles.contains(auth.userRole)) {
      return const LoginScreen();
    }

    // First login: franchise partners must accept the terms & conditions.
    if (auth.user?.needsTermsAcceptance == true && child is! TermsScreen) {
      return const TermsScreen();
    }

    return child;
  }
}
