import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/enums/app_enums.dart';
import '../../core/refresh/auto_refresh_mixin.dart';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../providers/inventory_provider.dart';
import '../../providers/payment_provider.dart';
import '../../providers/notification_provider.dart';
import '../../widgets/common/logout_dialog.dart';
import '../../services/notification_service.dart';
import 'staff_dashboard.dart';
import '../staff/orders/staff_order_list_screen.dart';
import '../staff/inventory/staff_inventory_screen.dart';
import '../shared/notifications/notification_screen.dart';
import '../shared/support/chat_screen.dart';
import '../../services/api/api_service.dart';
import '../../services/print_service.dart';
import '../../widgets/common/exit_dialog.dart';

class StaffShell extends StatefulWidget {
  const StaffShell({super.key});

  @override
  State<StaffShell> createState() => _StaffShellState();
}

class _StaffShellState extends State<StaffShell>
    with AutoRefreshMixin<StaffShell> {
  int _currentTab = 0;
  bool _isExiting = false;
  OrderStatus? _ordersFilter;
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
    context.read<OrderProvider>().loadOrders();
    context.read<InventoryProvider>().loadInventory();
    context.read<PaymentProvider>().loadPayments();
    context.read<NotificationProvider>().loadNotifications();
  }

  @override
  void onAutoRefresh() {
    if (!mounted) return;
    context.read<OrderProvider>().loadOrders(silent: true);
    context.read<InventoryProvider>().loadInventory(silent: true);
    context.read<PaymentProvider>().loadPayments(silent: true);
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
        style: NotificationStyle.order,
        onTap: () => _switchTab(3),
      );
    });
  }

  void _switchTab(int index) {
    if (index == _currentTab) return;
    if (index != 1) _ordersFilter = null;
    _tabHistory.add(_currentTab);
    if (_tabHistory.length > 50) _tabHistory.removeAt(0);
    _setTab(index);
  }

  void _setTab(int index) {
    setState(() => _currentTab = index);
    _tabNotifier.value = index;
    _refreshTabData(index);
  }

  void _switchToOrders(OrderStatus? filter) {
    _ordersFilter = filter;
    _switchTab(1);
  }

  void _refreshTabData(int index) {
    if (!mounted) return;
    if (index == 2) {
      context.read<InventoryProvider>().loadInventory(silent: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return StaffTabScope(
      tabNotifier: _tabNotifier,
      onSwitchTab: _switchTab,
      onSwitchToOrders: _switchToOrders,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (_, __) => _handleBack(),
        child: Scaffold(
          appBar: AppBar(
            title: Text(_getAppBarTitle()),
            actions: [
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
                        if (notifier.unreadCount == 0)
                          return const SizedBox.shrink();
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
          bottomNavigationBar: BottomNavigationBar(
            currentIndex: _currentTab,
            onTap: _switchTab,
            type: BottomNavigationBarType.fixed,
            selectedItemColor: AppColors.primaryGreen,
            unselectedItemColor: AppColors.textLight,
            selectedLabelStyle: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
            unselectedLabelStyle: const TextStyle(fontSize: 11),
            items: const [
              BottomNavigationBarItem(
                icon: Icon(Icons.dashboard_outlined),
                activeIcon: Icon(Icons.dashboard),
                label: 'Home',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.assignment_outlined),
                activeIcon: Icon(Icons.assignment),
                label: 'Orders',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.warehouse_outlined),
                activeIcon: Icon(Icons.warehouse),
                label: 'Inventory',
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
        return 'Staff Dashboard';
      case 1:
        return 'Order Management';
      case 2:
        return 'Inventory Monitoring';
      case 3:
        return 'Notifications';
      case 4:
        return 'Profile';
      default:
        return 'Farmmantra Staff';
    }
  }

  Widget _buildBody() {
    switch (_currentTab) {
      case 0:
        return const StaffDashboard();
      case 1:
        return StaffOrderListScreen(initialFilter: _ordersFilter);
      case 2:
        return const StaffInventoryScreen();
      case 3:
        return const NotificationScreen();
      case 4:
        return const _StaffProfileTab();
      default:
        return const StaffDashboard();
    }
  }
}

class StaffTabScope extends InheritedWidget {
  final ValueNotifier<int> tabNotifier;
  final void Function(int) onSwitchTab;
  final void Function(OrderStatus?) onSwitchToOrders;

  const StaffTabScope({
    super.key,
    required this.tabNotifier,
    required this.onSwitchTab,
    required this.onSwitchToOrders,
    required super.child,
  });

  static StaffTabScope? of(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<StaffTabScope>();
  }

  @override
  bool updateShouldNotify(StaffTabScope oldWidget) {
    return tabNotifier != oldWidget.tabNotifier;
  }
}

class _StaffProfileTab extends StatelessWidget {
  const _StaffProfileTab();

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const SizedBox(height: 16),
          CircleAvatar(
            radius: 40,
            backgroundColor: AppColors.primaryGreen.withAlpha(26),
            child: Text(
              user?.initials ?? '?',
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w700,
                color: AppColors.primaryGreen,
              ),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            user?.name ?? '',
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            user?.email ?? '',
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.info.withAlpha(26),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              user?.role.displayName ?? '',
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: AppColors.info,
              ),
            ),
          ),
          const SizedBox(height: 32),
          _buildMenuItem(
            context,
            Icons.person_outline,
            'Edit Profile',
            () => _showEditProfileDialog(context, user),
          ),
          _buildMenuItem(
            context,
            Icons.lock_outline,
            'Change Password',
            () => _showChangePasswordDialog(context),
          ),
          _buildMenuItem(
            context,
            Icons.confirmation_number_outlined,
            'Support Tickets & Chat',
            () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ChatScreen()),
            ),
          ),
          _buildMenuItem(
            context,
            Icons.print_outlined,
            'Print Company Stock Report',
            () => PrintService.open(
              context,
              context.read<ApiService>(),
              'stock-report',
              '0',
              query: const {'scope': 'company'},
            ),
          ),
          _buildMenuItem(
            context,
            Icons.help_outline,
            'Help & Support',
            () => _showHelpSupportDialog(context),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () async {
                final confirmed = await confirmLogout(context);
                if (!confirmed || !context.mounted) return;
                context.read<AuthProvider>().logout();
                Navigator.of(context).pushReplacementNamed('/login');
              },
              icon: const Icon(Icons.logout, color: AppColors.error),
              label: const Text(
                'Logout',
                style: TextStyle(color: AppColors.error),
              ),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: AppColors.error),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuItem(
    BuildContext context,
    IconData icon,
    String label,
    VoidCallback onTap,
  ) {
    return ListTile(
      leading: Icon(icon, color: AppColors.textSecondary),
      title: Text(label),
      trailing: const Icon(Icons.chevron_right, color: AppColors.textLight),
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(horizontal: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
    );
  }

  void _showEditProfileDialog(BuildContext context, user) {
    final nameController = TextEditingController(text: user?.name ?? '');
    final phoneController = TextEditingController(text: user?.phone ?? '');

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Edit Staff Profile'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameController,
              decoration: const InputDecoration(labelText: 'Full Name'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: phoneController,
              decoration: const InputDecoration(labelText: 'Phone Number'),
              keyboardType: TextInputType.phone,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Profile updated successfully!'),
                  backgroundColor: AppColors.success,
                ),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primaryGreen,
            ),
            child: const Text(
              'Save Changes',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  void _showChangePasswordDialog(BuildContext context) {
    final oldPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Change Password'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: oldPasswordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Current Password'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: newPasswordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'New Password'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: confirmPasswordController,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Confirm New Password',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              if (newPasswordController.text.length < 6) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Password must be at least 6 characters'),
                    backgroundColor: AppColors.error,
                  ),
                );
                return;
              }
              if (newPasswordController.text !=
                  confirmPasswordController.text) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Passwords do not match'),
                    backgroundColor: AppColors.error,
                  ),
                );
                return;
              }
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Password changed successfully!'),
                  backgroundColor: AppColors.success,
                ),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primaryGreen,
            ),
            child: const Text(
              'Update Password',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  void _showHelpSupportDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.headset_mic, color: AppColors.primaryGreen),
            SizedBox(width: 8),
            Text('Help & Support'),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.warning.withAlpha(25),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.warning.withAlpha(80)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.admin_panel_settings, color: AppColors.warning),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Lost or forgot your password? Please contact your System Administrator to reset your account password.',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              const ListTile(
                leading: Icon(
                  Icons.email_outlined,
                  color: AppColors.primaryGreen,
                ),
                title: Text(
                  'Support Email',
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                subtitle: Text(
                  'support@farmmantra.com',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                contentPadding: EdgeInsets.zero,
              ),
              const ListTile(
                leading: Icon(
                  Icons.phone_outlined,
                  color: AppColors.primaryGreen,
                ),
                title: Text(
                  'Helpline / Admin Desk',
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                subtitle: Text(
                  '+234 (0) 800-FARM-MAN',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                contentPadding: EdgeInsets.zero,
              ),
              const ListTile(
                leading: Icon(
                  Icons.schedule_outlined,
                  color: AppColors.primaryGreen,
                ),
                title: Text(
                  'Support Hours',
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                subtitle: Text(
                  'Mon - Sat: 8:00 AM - 6:00 PM',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                contentPadding: EdgeInsets.zero,
              ),
            ],
          ),
        ),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.pop(context),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primaryGreen,
            ),
            child: const Text('Close', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }
}
