import '../../features/operations/presentation/operations_home.dart';
import '../../features/operations/presentation/knowledge_screen.dart';
import '../../features/operations/presentation/workspace_ui.dart';
import '../../widgets/common/workspace_bottom_bar.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/refresh/auto_refresh_mixin.dart';
import '../../providers/auth_provider.dart';
import '../../providers/payment_provider.dart';
import '../../providers/notification_provider.dart';
import '../../services/notification_service.dart';
import 'finance_dashboard.dart';
import 'payments/payment_list_screen.dart';
import '../shared/notifications/notification_screen.dart';
import '../shared/profile/profile_screen.dart';
import '../../widgets/common/exit_dialog.dart';
import 'package:flutter/services.dart';

class FinanceShell extends StatefulWidget {
  const FinanceShell({super.key});

  @override
  State<FinanceShell> createState() => _FinanceShellState();
}

class _FinanceShellState extends State<FinanceShell>
    with AutoRefreshMixin<FinanceShell> {
  int _currentTab = 0;
  bool _isExiting = false;
  final List<int> _tabHistory = [];
  late final ValueNotifier<int> _tabNotifier;

  @override
  void initState() {
    super.initState();
    _tabNotifier = ValueNotifier(_currentTab);
    context.read<NotificationProvider>().addListener(_showIncomingNotification);
    startAutoRefresh();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadInitialData();
    });
  }

  void _loadInitialData() {
    context.read<PaymentProvider>().loadFinanceDashboard();
    context.read<PaymentProvider>().loadFinancePayments();
    context.read<PaymentProvider>().loadPendingFinancePayments();
    context.read<NotificationProvider>().loadNotifications();
  }

  @override
  void onAutoRefresh() {
    if (!mounted) return;
    context.read<PaymentProvider>().loadFinanceDashboard(silent: true);
    context.read<PaymentProvider>().loadFinancePayments(silent: true);
    context.read<PaymentProvider>().loadPendingFinancePayments(silent: true);
    context.read<NotificationProvider>().loadNotifications(silent: true);
    context.read<NotificationProvider>().refreshUnreadCount();
  }

  @override
  void dispose() {
    context.read<NotificationProvider>().removeListener(
      _showIncomingNotification,
    );
    _tabNotifier.dispose();
    super.dispose();
  }

  void _showIncomingNotification() {
    final notification = context.read<NotificationProvider>().takeLatestAlert();
    if (notification == null || !mounted) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      NotificationService().show(
        context: context,
        title: notification.title,
        message: notification.message,
        style: NotificationStyle.info,
        onTap: () => _switchTab(3),
      );
    });
  }

  void _switchTab(int index) {
    if (index == _currentTab) return;
    _tabHistory.add(_currentTab);
    if (_tabHistory.length > 50) _tabHistory.removeAt(0);
    _setTab(index);
  }

  void _setTab(int index) {
    setState(() => _currentTab = index);
    _tabNotifier.value = index;
    _refreshTabData(index);
  }

  void _refreshTabData(int index) {
    if (!mounted) return;
    if (index == 1) {
      context.read<PaymentProvider>().loadPendingFinancePayments(silent: true);
    } else if (index == 2) {
      context.read<PaymentProvider>().loadFinancePayments(silent: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FinanceTabScope(
      tabNotifier: _tabNotifier,
      onSwitchTab: _switchTab,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (_, _) => _handleBack(),
        child: Scaffold(
          appBar: AppBar(
            title: Text(_getAppBarTitle()),
            actions: [
              IconButton(
                tooltip: 'My workspace',
                icon: const Icon(Icons.grid_view_rounded),
                onPressed: () =>
                    openWorkspace(context, const OperationsHubScreen()),
              ),
              IconButton(
                tooltip: 'Knowledge library',
                icon: const Icon(Icons.menu_book_outlined),
                onPressed: () =>
                    openWorkspace(context, const KnowledgeScreen()),
              ),
              Stack(
                children: [
                  IconButton(
                    icon: const Icon(Icons.notifications_outlined),
                    onPressed: () => _switchTab(3),
                  ),
                  Positioned(
                    right: 8,
                    top: 8,
                    child: Consumer<NotificationProvider>(
                      builder: (_, notifier, child) {
                        if (notifier.unreadCount == 0) {
                          return const SizedBox.shrink();
                        }
                        return Container(
                          padding: const EdgeInsets.all(4),
                          decoration: const BoxDecoration(
                            color: AppColors.error,
                            shape: BoxShape.circle,
                          ),
                          child: Text(
                            notifier.unreadCount > 9
                                ? '9+'
                                : '${notifier.unreadCount}',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ],
          ),
          body: _buildBody(),
          bottomNavigationBar: WorkspaceBottomBar(
            currentIndex: _currentTab,
            onTap: _switchTab,
            items: const [
              BottomNavigationBarItem(
                icon: Icon(Icons.dashboard_outlined),
                activeIcon: Icon(Icons.dashboard),
                label: 'Home',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.payments_outlined),
                activeIcon: Icon(Icons.payments),
                label: 'Payments',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.receipt_long_outlined),
                activeIcon: Icon(Icons.receipt_long),
                label: 'History',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.notifications_outlined),
                activeIcon: Icon(Icons.notifications),
                label: 'Alerts',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.person_outline),
                activeIcon: Icon(Icons.person),
                label: 'Profile',
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _handleBack() async {
    if (_isExiting) return;
    if (_tabHistory.isNotEmpty) {
      _setTab(_tabHistory.removeLast());
      return;
    }
    _isExiting = true;
    final shouldExit = await confirmExit(context);
    if (!mounted) return;
    if (shouldExit) {
      await context.read<AuthProvider>().logout();
      await SystemNavigator.pop();
    }
    _isExiting = false;
  }

  String _getAppBarTitle() {
    switch (_currentTab) {
      case 0:
        return 'Finance Dashboard';
      case 1:
        return 'Pending Payments';
      case 2:
        return 'Payment History';
      case 3:
        return 'Notifications';
      case 4:
        return 'Profile';
      default:
        return 'Finance Department';
    }
  }

  Widget _buildBody() {
    switch (_currentTab) {
      case 0:
        return const FinanceDashboard();
      case 1:
        return PaymentListScreen(statusFilter: 'pending');
      case 2:
        return const PaymentListScreen();
      case 3:
        return const NotificationScreen();
      case 4:
        return const ProfileScreen(showAppBar: false);
      default:
        return const FinanceDashboard();
    }
  }
}

class FinanceTabScope extends InheritedWidget {
  final ValueNotifier<int> tabNotifier;
  final void Function(int) onSwitchTab;

  const FinanceTabScope({
    super.key,
    required this.tabNotifier,
    required this.onSwitchTab,
    required super.child,
  });

  static FinanceTabScope? of(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<FinanceTabScope>();
  }

  @override
  bool updateShouldNotify(FinanceTabScope oldWidget) {
    return tabNotifier != oldWidget.tabNotifier;
  }
}
