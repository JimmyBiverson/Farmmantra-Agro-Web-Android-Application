import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/refresh/auto_refresh_mixin.dart';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../providers/inventory_provider.dart';
import '../../providers/payment_provider.dart';
import '../../providers/notification_provider.dart';
import '../../providers/product_provider.dart';
import '../../providers/customer_provider.dart';
import 'franchise_dashboard.dart';
import '../franchise/orders/order_list_screen.dart';
import '../franchise/orders/create_order_screen.dart';
import '../franchise/products/product_catalog_screen.dart';
import '../franchise/inventory/inventory_screen.dart';
import '../franchise/payments/payment_screen.dart';
import '../franchise/customers/customer_list_screen.dart';
import '../franchise/sales/create_sale_screen.dart';
import '../../services/api/api_service.dart';
import '../../services/print_service.dart';
import '../shared/terms/terms_screen.dart';
import '../shared/notifications/notification_screen.dart';
import '../../widgets/common/logout_dialog.dart';
import '../shared/profile/profile_screen.dart';
import '../shared/support/chat_screen.dart';
import '../../services/notification_service.dart';
import '../../widgets/common/exit_dialog.dart';
import 'package:flutter/services.dart';

class FranchiseShell extends StatefulWidget {
  const FranchiseShell({super.key});

  @override
  State<FranchiseShell> createState() => _FranchiseShellState();
}

class _FranchiseShellState extends State<FranchiseShell>
    with AutoRefreshMixin<FranchiseShell> {
  int _currentTab = 0;
  bool _isExiting = false;
  final List<int> _tabHistory = [];
  late final ValueNotifier<int> _tabNotifier;
  late final NotificationProvider _notificationProvider;

  @override
  void initState() {
    super.initState();
    _tabNotifier = ValueNotifier(_currentTab);
    _notificationProvider = context.read<NotificationProvider>();
    _notificationProvider.addListener(_showIncomingNotification);
    startAutoRefresh();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadInitialData();
    });
  }

  void _loadInitialData() {
    context.read<ProductProvider>().loadCategories();
    context.read<ProductProvider>().loadProducts();
    context.read<OrderProvider>().loadOrders();
    context.read<InventoryProvider>().loadInventory();
    context.read<PaymentProvider>().loadPayments();
    context.read<PaymentProvider>().loadAccountSummary();
    context.read<CustomerProvider>().loadCustomers();
    context.read<NotificationProvider>().loadNotifications();
  }

  @override
  void onAutoRefresh() {
    if (!mounted) return;
    final productProvider = context.read<ProductProvider>();
    productProvider.loadCategories(silent: true);
    productProvider.loadProducts(
      categoryId: productProvider.selectedCategory,
      silent: true,
      resetFilters: false,
    );
    context.read<OrderProvider>().loadOrders(silent: true);
    context.read<InventoryProvider>().loadInventory(silent: true);
    context.read<PaymentProvider>().loadPayments(silent: true);
    context.read<PaymentProvider>().loadAccountSummary(silent: true);
    context.read<CustomerProvider>().loadCustomers(silent: true);
    context.read<NotificationProvider>().loadNotifications(silent: true);
    context.read<NotificationProvider>().refreshUnreadCount();
  }

  @override
  void dispose() {
    _notificationProvider.removeListener(_showIncomingNotification);
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
        onTap: () => Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const NotificationScreen())),
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
    switch (index) {
      case 0:
        context.read<PaymentProvider>().loadAccountSummary(silent: true);
        break;
      case 3:
        context.read<InventoryProvider>().loadInventory(silent: true);
        break;
      case 4:
        context.read<PaymentProvider>().loadAccountSummary(silent: true);
        break;
    }
  }

  Widget _buildCartAction() {
    return Consumer<OrderProvider>(
      builder: (context, provider, _) {
        final count = provider.cartItems.values.fold(
          0,
          (sum, qty) => sum + qty,
        );
        return Stack(
          clipBehavior: Clip.none,
          children: [
            IconButton(
              icon: const Icon(Icons.shopping_cart_outlined),
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const CreateOrderScreen()),
                );
              },
              tooltip: 'Cart',
            ),
            if (count > 0)
              Positioned(
                right: 6,
                top: 6,
                child: Container(
                  padding: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(
                    color: AppColors.error,
                    shape: BoxShape.circle,
                  ),
                  constraints: const BoxConstraints(
                    minWidth: 18,
                    minHeight: 18,
                  ),
                  child: Text(
                    count > 9 ? '9+' : '$count',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 9,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return FranchiseTabScope(
      tabNotifier: _tabNotifier,
      onSwitchTab: _switchTab,
      child: PopScope(
        canPop: false,
        onPopInvokedWithResult: (_, __) => _handleBack(),
        child: Scaffold(
          appBar: AppBar(
            title: Text(_getAppBarTitle()),
            actions: [
              _buildCartAction(),
              IconButton(
                icon: const Icon(Icons.headset_mic_outlined),
                onPressed: () {
                  Navigator.of(
                    context,
                  ).push(MaterialPageRoute(builder: (_) => const ChatScreen()));
                },
                tooltip: 'Support',
              ),
              Stack(
                children: [
                  IconButton(
                    icon: const Icon(Icons.notifications_outlined),
                    onPressed: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const NotificationScreen(),
                        ),
                      );
                    },
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
          floatingActionButton: _currentTab == 0
              ? FloatingActionButton.extended(
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const CreateOrderScreen(),
                      ),
                    );
                  },
                  backgroundColor: AppColors.primaryGreen,
                  foregroundColor: Colors.white,
                  icon: const Icon(Icons.add_shopping_cart),
                  label: const Text('New Order'),
                )
              : null,
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
                label: 'Dashboard',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.shopping_bag_outlined),
                activeIcon: Icon(Icons.shopping_bag),
                label: 'Orders',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.storefront_outlined),
                activeIcon: Icon(Icons.storefront),
                label: 'Products',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.inventory_2_outlined),
                activeIcon: Icon(Icons.inventory_2),
                label: 'Stock',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.person_outline),
                activeIcon: Icon(Icons.person),
                label: 'More',
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
        return 'Dashboard';
      case 1:
        return 'Orders';
      case 2:
        return 'Products';
      case 3:
        return 'Inventory';
      case 4:
        return 'More';
      default:
        return 'Farmmantra';
    }
  }

  Widget _buildBody() {
    switch (_currentTab) {
      case 0:
        return const FranchiseDashboard();
      case 1:
        return const OrderListScreen();
      case 2:
        return const ProductCatalogScreen();
      case 3:
        return const InventoryScreen();
      case 4:
        return const _MoreTab();
      default:
        return const FranchiseDashboard();
    }
  }
}

class FranchiseTabScope extends InheritedWidget {
  final ValueNotifier<int> tabNotifier;
  final void Function(int) onSwitchTab;

  const FranchiseTabScope({
    super.key,
    required this.tabNotifier,
    required this.onSwitchTab,
    required super.child,
  });

  static FranchiseTabScope? of(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<FranchiseTabScope>();
  }

  @override
  bool updateShouldNotify(FranchiseTabScope oldWidget) {
    return tabNotifier != oldWidget.tabNotifier;
  }
}

class _MoreTab extends StatelessWidget {
  const _MoreTab();

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final avatarUrl = user?.avatarUrl;
    final hasAvatar = avatarUrl != null && avatarUrl.isNotEmpty;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: AppColors.primaryGreen,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: Colors.white.withAlpha(51),
                  backgroundImage: hasAvatar ? NetworkImage(avatarUrl) : null,
                  child: hasAvatar
                      ? null
                      : Text(
                          user?.initials ?? '?',
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.name ?? '',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        user?.franchiseName ?? user?.email ?? '',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.white70,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => _openProfile(context),
                  icon: const Icon(Icons.chevron_right, color: Colors.white70),
                  tooltip: 'View profile',
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          _buildMenuItem(
            context,
            Icons.account_circle_outlined,
            'My Profile',
            () {
              _openProfile(context);
            },
          ),
          _buildMenuItem(
            context,
            Icons.shopping_cart_outlined,
            'New Order',
            () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const CreateOrderScreen()),
              );
            },
          ),
          _buildMenuItem(
            context,
            Icons.storefront_outlined,
            'Product Catalog',
            () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => Scaffold(
                    appBar: AppBar(title: const Text('Product Catalog')),
                    body: const ProductCatalogScreen(),
                  ),
                ),
              );
            },
          ),
          _buildMenuItem(context, Icons.people_outline, 'Customers', () {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => Scaffold(
                  appBar: AppBar(title: const Text('Customer Directory')),
                  body: const CustomerListScreen(),
                ),
              ),
            );
          }),
          _buildMenuItem(context, Icons.point_of_sale_outlined, 'Sales', () {
            Navigator.of(
              context,
            ).push(MaterialPageRoute(builder: (_) => const CreateSaleScreen()));
          }),
          _buildMenuItem(
            context,
            Icons.account_balance_wallet_outlined,
            'Payments',
            () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => Scaffold(
                    appBar: AppBar(
                      title: const Text('Payments & Transactions'),
                    ),
                    body: const PaymentScreen(),
                  ),
                ),
              );
            },
          ),
          _buildMenuItem(
            context,
            Icons.receipt_long_outlined,
            'Account Statement',
            () => Navigator.of(context).pushNamed('/franchise/statement'),
          ),
          _buildMenuItem(
            context,
            Icons.print_outlined,
            'Print My Stock Report',
            () => PrintService.open(
              context,
              context.read<ApiService>(),
              'stock-report',
              context.read<AuthProvider>().user?.franchiseId ?? '0',
              query: const {'scope': 'franchise'},
            ),
          ),
          _buildMenuItem(
            context,
            Icons.description_outlined,
            'Terms & Conditions',
            () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const TermsScreen(readOnly: true)),
            ),
          ),
          _buildMenuItem(
            context,
            Icons.notifications_outlined,
            'Notifications',
            () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const NotificationScreen()),
              );
            },
          ),
          _buildMenuItem(
            context,
            Icons.headset_mic_outlined,
            'Support & Chat',
            () {
              Navigator.of(
                context,
              ).push(MaterialPageRoute(builder: (_) => const ChatScreen()));
            },
          ),
          const SizedBox(height: 20),
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

  void _openProfile(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const ProfileScreen()));
  }

  Widget _buildMenuItem(
    BuildContext context,
    IconData icon,
    String label,
    VoidCallback onTap,
  ) {
    return Card(
      margin: const EdgeInsets.only(bottom: 4),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: AppColors.primaryGreen.withAlpha(26),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: AppColors.primaryGreen, size: 20),
        ),
        title: Text(
          label,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
        ),
        trailing: const Icon(Icons.chevron_right, color: AppColors.textLight),
        onTap: onTap,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }
}
