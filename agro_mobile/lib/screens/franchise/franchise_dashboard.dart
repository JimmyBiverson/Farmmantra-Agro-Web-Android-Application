import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/responsive.dart';
import '../../core/utils/formatters.dart';
import '../../core/enums/app_enums.dart';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../providers/inventory_provider.dart';
import '../../providers/product_provider.dart';
import '../../widgets/common/app_card.dart';
import '../../widgets/common/section_header.dart';
import '../../widgets/common/status_badge.dart';
import '../../widgets/common/loading_view.dart';
import 'franchise_shell.dart';
import 'orders/create_order_screen.dart';
import 'orders/order_detail_screen.dart';
import 'sales/create_sale_screen.dart';
import '../../services/api/api_service.dart';

class FranchiseDashboard extends StatefulWidget {
  const FranchiseDashboard({super.key});

  @override
  State<FranchiseDashboard> createState() => _FranchiseDashboardState();
}

class _FranchiseDashboardState extends State<FranchiseDashboard> {
  bool _isLoading = true;
  final PageController _pageController = PageController();
  int _currentBannerIndex = 0;
  Timer? _autoScrollTimer;

  // Banner slides — images served from admin controlled URLs (can be overridden by API later)
  // The admin updates slides via /admin/slides panel in the web dashboard
  static const List<Map<String, dynamic>> _bannerSlides = [
    {
      'image': 'assets/images/banner_1.png',
      'tag': 'CROP PROTECTION',
      'title': 'Authentic Agro Chemicals',
      'subtitle': 'Herbicides, Fungicides & Insecticides',
    },
    {
      'image': 'assets/images/banner_2.png',
      'tag': 'BULK DISCOUNTS',
      'title': 'Tier Pricing & Credit Terms',
      'subtitle': 'Exclusive savings for franchise partners',
    },
    {
      'image': null,
      'tag': 'FAST DELIVERY',
      'title': 'Countrywide Logistics',
      'subtitle': 'Real-time tracking & inventory sync',
      'color': 0xFF1565C0,
    },
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadData());
  }

  @override
  void dispose() {
    _autoScrollTimer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  void _startAutoScroll() {
    _autoScrollTimer?.cancel();
    _autoScrollTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted || !_pageController.hasClients) return;
      final next = (_currentBannerIndex + 1) % _bannerSlides.length;
      _pageController.animateToPage(
        next,
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeInOut,
      );
    });
  }

  Future<void> _loadData() async {
    final orderProvider = context.read<OrderProvider>();
    final invProvider = context.read<InventoryProvider>();
    final productProvider = context.read<ProductProvider>();

    try {
      await Future.wait([
        orderProvider.loadOrders(),
        invProvider.loadInventory(),
        productProvider.loadProducts(),
      ]);
    } catch (_) {}

    if (!mounted) return;
    setState(() => _isLoading = false);
    _startAutoScroll();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: EdgeInsets.symmetric(
        horizontal: Responsive.horizontalPadding(context),
        vertical: 16,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildWelcomeHeader(),
          const SizedBox(height: 16),
          if (_isLoading)
            const SizedBox(height: 200, child: Center(child: LoadingView()))
          else ...[
            const MonthSummaryCard(),
            const SizedBox(height: 12),
            _buildStatsGrid(context),
            const SizedBox(height: 8),
            SectionHeader(
              title: 'Recent Orders',
              actionLabel: 'View All',
              onAction: () => FranchiseTabScope.of(context)?.onSwitchTab(1),
            ),
            _buildRecentOrders(),
            const SizedBox(height: 8),
            SectionHeader(
              title: 'Featured Products',
              actionLabel: 'See All',
              onAction: () => FranchiseTabScope.of(context)?.onSwitchTab(2),
            ),
            _buildFeaturedProducts(),
            const SizedBox(height: 8),
            SectionHeader(
              title: 'Inventory Alerts',
              actionLabel: 'View All',
              onAction: () => FranchiseTabScope.of(context)?.onSwitchTab(3),
            ),
            _buildInventoryAlerts(),
          ],
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildWelcomeHeader() {
    final user = context.watch<AuthProvider>().user;
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.primaryGreenDark,
            AppColors.primaryGreen,
            Color(0xFF388E3C),
          ],
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.primaryGreen.withAlpha(60),
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Greeting Row ─────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white.withAlpha(38),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.agriculture_rounded,
                    color: Colors.white,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Flexible(
                        child: Text(
                          'Hi, ${user?.name ?? 'Partner'} 👋',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          '· ${user?.franchiseName ?? 'Farmmantra'}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: Colors.white60,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // ── Image Banner Slider ──────────────────────────────────
          SizedBox(
            height: 130,
            child: PageView.builder(
              controller: _pageController,
              onPageChanged: (idx) => setState(() => _currentBannerIndex = idx),
              itemCount: _bannerSlides.length,
              itemBuilder: (context, index) => _buildBannerSlide(index),
            ),
          ),

          // ── Indicator Dots ───────────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(
                _bannerSlides.length,
                (i) => AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: _currentBannerIndex == i ? 20 : 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: _currentBannerIndex == i
                        ? Colors.white
                        : Colors.white38,
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              ),
            ),
          ),

          // ── Quick Actions ────────────────────────────────────────
          const Divider(color: Colors.white24, height: 1),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              child: Row(
                children: [
                  _buildQuickActionButton(
                    icon: Icons.add_shopping_cart,
                    label: 'New Order',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const CreateOrderScreen(),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  _buildQuickActionButton(
                    icon: Icons.storefront_outlined,
                    label: 'Products',
                    onTap: () => FranchiseTabScope.of(context)?.onSwitchTab(2),
                  ),
                  const SizedBox(width: 8),
                  _buildQuickActionButton(
                    icon: Icons.point_of_sale_outlined,
                    label: 'New Sale',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const CreateSaleScreen(),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  _buildQuickActionButton(
                    icon: Icons.inventory_2_outlined,
                    label: 'Stock Alerts',
                    onTap: () => FranchiseTabScope.of(context)?.onSwitchTab(3),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBannerSlide(int index) {
    final slide = _bannerSlides[index];
    final hasImage = slide['image'] != null;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Background: actual image or solid color fallback
            if (hasImage)
              Image.asset(
                slide['image'] as String,
                fit: BoxFit.cover,
                errorBuilder: (_, e, s) => Container(
                  color: Color(slide['color'] as int? ?? 0xFF1B5E20),
                ),
              )
            else
              Container(color: Color(slide['color'] as int? ?? 0xFF1B5E20)),

            // Gradient overlay for text legibility
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.centerRight,
                  end: Alignment.centerLeft,
                  colors: [Colors.transparent, Colors.black.withAlpha(180)],
                ),
              ),
            ),

            // Text content
            Positioned(
              left: 14,
              bottom: 14,
              right: 60,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 7,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.primaryGreen.withAlpha(200),
                      borderRadius: BorderRadius.circular(5),
                    ),
                    child: Text(
                      slide['tag'] as String,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 9,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.6,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    slide['title'] as String,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      shadows: [Shadow(blurRadius: 4, color: Colors.black54)],
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    slide['subtitle'] as String,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 11,
                      shadows: [Shadow(blurRadius: 3, color: Colors.black38)],
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickActionButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white.withAlpha(28),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withAlpha(40)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: Colors.white, size: 16),
            const SizedBox(width: 6),
            Text(
              label,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w600,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatsGrid(BuildContext context) {
    final pendingCount = context
        .read<OrderProvider>()
        .orders
        .where((o) => o.statusEnum.name == 'pending')
        .length;
    final inventory = context.read<InventoryProvider>().items;
    final totalInventoryValue = inventory.fold(
      0.0,
      (sum, i) => sum + i.totalValue,
    );

    final stats = [
      StatCard(
        title: 'Pending Orders',
        value: '$pendingCount',
        icon: Icons.shopping_cart_outlined,
        iconColor: AppColors.warning,
        subtitle: 'Tap to view',
        onTap: () => FranchiseTabScope.of(context)?.onSwitchTab(1),
      ),
      StatCard(
        title: 'Inv. Value',
        value: Formatters.currency(totalInventoryValue),
        icon: Icons.inventory_2_outlined,
        iconColor: AppColors.info,
        subtitle: 'Tap for details',
        onTap: () => FranchiseTabScope.of(context)?.onSwitchTab(3),
      ),
      StatCard(
        title: 'Total Items',
        value: '${inventory.length}',
        icon: Icons.inventory,
        iconColor: AppColors.primaryGreen,
        subtitle: 'Tap to view',
        onTap: () => FranchiseTabScope.of(context)?.onSwitchTab(3),
      ),
      StatCard(
        title: 'Low Stock',
        value:
            '${inventory.where((i) => i.isLowStock || i.isOutOfStock).length}',
        icon: Icons.warning_amber_outlined,
        iconColor: AppColors.error,
        subtitle: 'Tap for alerts',
        onTap: () => FranchiseTabScope.of(context)?.onSwitchTab(3),
      ),
    ];

    if (Responsive.isDesktop(context)) {
      return Row(children: stats.map((s) => Expanded(child: s)).toList());
    }

    return GridView.count(
      crossAxisCount: Responsive.isTablet(context) ? 4 : 2,
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 1.5,
      children: stats,
    );
  }

  Widget _buildRecentOrders() {
    final orders = context.read<OrderProvider>().orders.take(5).toList();

    if (orders.isEmpty) {
      return const AppCard(
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'No orders yet',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
        ),
      );
    }

    return Column(
      children: orders.map((order) {
        return AppCard(
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => OrderDetailScreen(orderId: order.id),
            ),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.primaryGreen.withAlpha(26),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.shopping_bag_outlined,
                  color: AppColors.primaryGreen,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.orderNumber ?? order.id,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      Formatters.date(order.createdAt),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    Formatters.currency(order.totalAmount),
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 4),
                  StatusBadge.fromOrderStatus(order.status),
                ],
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _buildFeaturedProducts() {
    // Sort by newest first (createdAt descending) and show latest 3
    final allProds = [...context.read<ProductProvider>().allProducts];
    allProds.sort((a, b) {
      final aDate = a.createdAt ?? DateTime(0);
      final bDate = b.createdAt ?? DateTime(0);
      return bDate.compareTo(aDate);
    });
    final products = allProds.take(3).toList();

    if (products.isEmpty) {
      return SizedBox(
        height: 148,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          itemCount: 3,
          itemBuilder: (context, i) => _buildPlaceholderProductCard(i),
        ),
      );
    }

    return SizedBox(
      height: 148,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        itemCount: products.length,
        itemBuilder: (context, index) {
          final product = products[index];
          final imageUrl = (product.imageUrls.isNotEmpty)
              ? product.imageUrls.first
              : product.imageUrl;

          return GestureDetector(
            onTap: () => FranchiseTabScope.of(context)?.onSwitchTab(2),
            child: Container(
              width: 120,
              margin: EdgeInsets.only(
                left: index == 0 ? 0 : 10,
                right: index == products.length - 1 ? 0 : 0,
              ),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withAlpha(13),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Product image
                  ClipRRect(
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(14),
                      topRight: Radius.circular(14),
                    ),
                    child: imageUrl != null && imageUrl.isNotEmpty
                        ? Image.network(
                            imageUrl,
                            height: 80,
                            width: double.infinity,
                            fit: BoxFit.cover,
                            errorBuilder: (_, e, s) =>
                                _buildProductImageFallback(index),
                          )
                        : _buildProductImageFallback(index),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(8, 5, 8, 5),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            product.name,
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textPrimary,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  Formatters.currency(product.standardPrice),
                                  style: const TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primaryGreen,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildProductImageFallback(int index) {
    final colors = [
      const Color(0xFF2E7D32),
      const Color(0xFF1565C0),
      const Color(0xFFE65100),
      const Color(0xFF6A1B9A),
    ];
    final icons = [Icons.grass, Icons.opacity, Icons.science, Icons.eco];
    return Container(
      height: 80,
      width: double.infinity,
      color: colors[index % colors.length].withAlpha(200),
      child: Icon(icons[index % icons.length], color: Colors.white54, size: 32),
    );
  }

  Widget _buildPlaceholderProductCard(int index) {
    final labels = ['Herbicide', 'Fungicide', 'Insecticide', 'Fertilizer'];
    final colors = [0xFF2E7D32, 0xFF1565C0, 0xFFE65100, 0xFF6A1B9A];
    return Container(
      width: 120,
      margin: EdgeInsets.only(left: index == 0 ? 0 : 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(13),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(14),
              topRight: Radius.circular(14),
            ),
            child: Container(
              height: 80,
              width: double.infinity,
              color: Color(colors[index % colors.length]).withAlpha(200),
              child: const Icon(Icons.eco, color: Colors.white54, size: 32),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 5, 8, 5),
              child: Text(
                labels[index % labels.length],
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInventoryAlerts() {
    final inventory = context
        .read<InventoryProvider>()
        .items
        .where((i) => i.isLowStock || i.isOutOfStock)
        .take(5)
        .toList();

    if (inventory.isEmpty) {
      return const AppCard(
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'No inventory alerts 🎉',
              style: TextStyle(color: AppColors.success),
            ),
          ),
        ),
      );
    }

    return AppCard(
      child: Column(
        children: inventory.asMap().entries.map((entry) {
          final item = entry.value;
          final detail = item.isOutOfStock
              ? 'Out of stock'
              : '${Formatters.quantity(item.quantity)} remaining';
          return Column(
            children: [
              _buildAlertItem(item.productName, detail, item.alertLevel),
              if (entry.key < inventory.length - 1) const Divider(height: 1),
            ],
          );
        }).toList(),
      ),
    );
  }

  Widget _buildAlertItem(
    String name,
    String detail,
    InventoryAlertLevel level,
  ) {
    Color color;
    IconData icon;
    switch (level) {
      case InventoryAlertLevel.outOfStock:
      case InventoryAlertLevel.critical:
        color = AppColors.error;
        icon = Icons.error_outline;
        break;
      case InventoryAlertLevel.low:
        color = AppColors.warning;
        icon = Icons.warning_amber_outlined;
        break;
      default:
        color = AppColors.success;
        icon = Icons.check_circle_outline;
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                Text(detail, style: TextStyle(fontSize: 12, color: color)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// This month at a glance (client request): sales, profit, number of sales,
/// stock value, pending orders and deliveries waiting to be accepted.
class MonthSummaryCard extends StatefulWidget {
  const MonthSummaryCard({super.key});

  @override
  State<MonthSummaryCard> createState() => _MonthSummaryCardState();
}

class _MonthSummaryCardState extends State<MonthSummaryCard> {
  Map<String, dynamic>? _stats;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final stats = await context.read<ApiService>().getDashboardStats();
    if (mounted) setState(() => _stats = stats);
  }

  static double _num(dynamic v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final s = _stats;
    Widget tile(String label, String value, IconData icon, Color color) => SizedBox(
      width: 150,
      child: Row(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                Text(
                  value,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'This month',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                ),
              ),
              TextButton.icon(
                onPressed: () => Navigator.of(context).pushNamed('/franchise/statement'),
                icon: const Icon(Icons.receipt_long_outlined, size: 18),
                label: const Text('Statement'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          if (s == null)
            const Padding(
              padding: EdgeInsets.all(12),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            )
          else
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                tile('Sales', Formatters.currency(_num(s['total_sales_this_month'])), Icons.point_of_sale, AppColors.primaryGreen),
                tile('Profit', Formatters.currency(_num(s['profit_this_month'])), Icons.trending_up, _num(s['profit_this_month']) < 0 ? AppColors.error : AppColors.success),
                tile('Sales made', '${_num(s['sales_count_this_month']).toInt()}', Icons.receipt_outlined, AppColors.info),
                tile('Stock value', Formatters.currency(_num(s['inventory_value'])), Icons.inventory_2_outlined, AppColors.info),
                tile('Pending orders', '${_num(s['total_orders_pending']).toInt()}', Icons.hourglass_top, AppColors.warning),
                tile('To accept', '${_num(s['awaiting_acceptance']).toInt()}', Icons.local_shipping_outlined, AppColors.primaryGreen),
                tile('Balance owed', Formatters.currency(_num(s['outstanding_balance'])), Icons.account_balance_wallet_outlined, AppColors.warning),
              ],
            ),
        ],
      ),
    );
  }
}
