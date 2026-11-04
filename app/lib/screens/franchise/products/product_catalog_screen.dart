import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/responsive.dart';
import '../../../models/product.dart';
import '../../../providers/order_provider.dart';
import '../../../providers/product_provider.dart';
import '../../../widgets/common/app_button.dart';
import '../../../widgets/common/app_text_field.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';
import '../../../widgets/common/product_image.dart';
import '../../../services/notification_service.dart';
import '../orders/create_order_screen.dart';

class ProductCatalogScreen extends StatefulWidget {
  const ProductCatalogScreen({super.key});

  @override
  State<ProductCatalogScreen> createState() => _ProductCatalogScreenState();
}

class _ProductCatalogScreenState extends State<ProductCatalogScreen> {
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    final provider = context.read<ProductProvider>();
    if (provider.categories.isEmpty) {
      provider.loadCategories();
    }
    provider.loadProducts(
      categoryId: provider.selectedCategory,
      silent: provider.products.isNotEmpty,
      resetFilters: false,
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            0,
          ),
          child: AppSearchField(
            controller: _searchController,
            hint: 'Search products...',
            onChanged: (q) => context.read<ProductProvider>().filterProducts(q),
            onClear: () => context.read<ProductProvider>().filterProducts(''),
          ),
        ),
        const SizedBox(height: AppConstants.smallPadding),
        _buildCategoryChips(),
        const SizedBox(height: AppConstants.smallPadding),
        Expanded(child: _buildProductList()),
        _buildMiniCartBar(),
      ],
    );
  }

  Widget _buildCategoryChips() {
    return Consumer<ProductProvider>(
      builder: (context, provider, _) {
        if (provider.categories.isEmpty) return const SizedBox.shrink();

        return SizedBox(
          height: 40,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(
              horizontal: AppConstants.defaultPadding,
            ),
            scrollDirection: Axis.horizontal,
            itemCount: provider.categories.length + 1,
            separatorBuilder: (context, index) => const SizedBox(width: 8),
            itemBuilder: (context, index) {
              if (index == 0) {
                final isSelected = provider.selectedCategory == null;
                return FilterChip(
                  label: const Text('All'),
                  selected: isSelected,
                  onSelected: (_) => provider.selectCategory(null),
                  selectedColor: AppColors.primaryGreen,
                  labelStyle: TextStyle(
                    color: isSelected ? Colors.white : AppColors.textPrimary,
                    fontWeight: FontWeight.w500,
                    fontSize: 12,
                  ),
                  checkmarkColor: Colors.white,
                  side: BorderSide.none,
                );
              }

              final cat = provider.categories[index - 1];
              final isSelected = provider.selectedCategory == cat.id;
              return FilterChip(
                label: Text(cat.name),
                selected: isSelected,
                onSelected: (_) => provider.selectCategory(cat.id),
                selectedColor: AppColors.primaryGreen,
                labelStyle: TextStyle(
                  color: isSelected ? Colors.white : AppColors.textPrimary,
                  fontWeight: FontWeight.w500,
                  fontSize: 12,
                ),
                checkmarkColor: Colors.white,
                side: BorderSide.none,
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildProductList() {
    return Consumer<ProductProvider>(
      builder: (context, provider, _) {
        if (provider.isLoading) {
          return const LoadingView(message: 'Loading products...');
        }

        if (provider.error != null) {
          return ErrorView(
            message: provider.error!,
            onRetry: () =>
                provider.loadProducts(categoryId: provider.selectedCategory),
          );
        }

        if (provider.products.isEmpty) {
          return const EmptyView(
            message: 'No products found',
            title: 'No Products',
            icon: Icons.inventory_2_outlined,
          );
        }

        final columns = Responsive.isTablet(context) ? 3 : 2;

        return RefreshIndicator(
          onRefresh: () =>
              provider.loadProducts(categoryId: provider.selectedCategory),
          child: GridView.builder(
            padding: const EdgeInsets.fromLTRB(
              AppConstants.defaultPadding,
              0,
              AppConstants.defaultPadding,
              AppConstants.defaultPadding + 80,
            ),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 0.62,
            ),
            itemCount: provider.products.length,
            itemBuilder: (context, index) =>
                _buildProductCard(provider.products[index]),
          ),
        );
      },
    );
  }

  Widget _buildProductCard(Product product) {
    return Consumer<OrderProvider>(
      builder: (context, orderProv, _) {
        final cartQty = orderProv.cartItems[product.id] ?? 0;
        final hasBulkDiscount = product.priceSlabs.length > 1;
        final cheapestSlab = hasBulkDiscount
            ? product.priceSlabs.reduce(
                (a, b) => a.pricePerUnit < b.pricePerUnit ? a : b,
              )
            : null;
        final savingPct = cheapestSlab != null && product.standardPrice > 0
            ? ((product.standardPrice - cheapestSlab.pricePerUnit) /
                      product.standardPrice *
                      100)
                  .round()
            : 0;

        return GestureDetector(
          onTap: () => _showPriceSlabSheet(product),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withAlpha(14),
                  blurRadius: 10,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Image Area ──────────────────────────────────────
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(14),
                  ),
                  child: Stack(
                    children: [
                      ProductImage(
                        imageUrl: product.imageUrl,
                        productName: product.name,
                        width: double.infinity,
                        height: 130,
                        fit: BoxFit.cover,
                        backgroundColor: const Color(0xFFF0F4F0),
                        borderRadius: 0,
                      ),
                      // Gradient overlay
                      Positioned(
                        bottom: 0,
                        left: 0,
                        right: 0,
                        child: Container(
                          height: 40,
                          decoration: const BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [Colors.transparent, Color(0x33000000)],
                            ),
                          ),
                        ),
                      ),
                      // Bulk discount badge
                      if (hasBulkDiscount && savingPct > 0)
                        Positioned(
                          top: 8,
                          left: 8,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 7,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(
                                colors: [Color(0xFFE65100), Color(0xFFF57C00)],
                              ),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              '$savingPct% OFF',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ),
                        ),
                      // Cart quantity badge
                      if (cartQty > 0)
                        Positioned(
                          top: 8,
                          right: 8,
                          child: Container(
                            width: 22,
                            height: 22,
                            decoration: const BoxDecoration(
                              color: AppColors.primaryGreen,
                              shape: BoxShape.circle,
                            ),
                            child: Center(
                              child: Text(
                                '$cartQty',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                // ── Content Area ─────────────────────────────────────
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Category chip
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.primaryGreen.withAlpha(22),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            product.categoryName,
                            style: const TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w600,
                              color: AppColors.primaryGreen,
                              letterSpacing: 0.3,
                            ),
                          ),
                        ),
                        const SizedBox(height: 4),
                        // Product name
                        Text(
                          product.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 12.5,
                            color: AppColors.textPrimary,
                            height: 1.2,
                          ),
                        ),
                        const SizedBox(height: 3),
                        // Unit of measure
                        Text(
                          'per ${product.unitOfMeasure}',
                          style: const TextStyle(
                            fontSize: 10,
                            color: AppColors.textSecondary,
                          ),
                        ),
                        const Spacer(),
                        // Price row
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              Formatters.currency(product.standardPrice),
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 13.5,
                                color: AppColors.primaryGreen,
                              ),
                            ),
                            if (hasBulkDiscount && cheapestSlab != null) ...[
                              const SizedBox(width: 4),
                              Text(
                                Formatters.currency(cheapestSlab.pricePerUnit),
                                style: TextStyle(
                                  fontSize: 10,
                                  color: Colors.grey.shade500,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 6),
                        // Add to Cart / Stepper
                        cartQty > 0
                            ? _buildStepper(product.id, cartQty)
                            : SizedBox(
                                width: double.infinity,
                                height: 34,
                                child: ElevatedButton(
                                  onPressed: () => _addToOrder(product),
                                  style: ElevatedButton.styleFrom(
                                    padding: EdgeInsets.zero,
                                    elevation: 0,
                                    backgroundColor: AppColors.primaryGreen,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                  ),
                                  child: const Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(
                                        Icons.add_shopping_cart_rounded,
                                        size: 14,
                                      ),
                                      SizedBox(width: 5),
                                      Text(
                                        'Add to Cart',
                                        style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
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
    );
  }

  Widget _buildStepper(String productId, int quantity) {
    final provider = context.read<OrderProvider>();
    return Container(
      height: 34,
      decoration: BoxDecoration(
        color: AppColors.primaryGreen.withAlpha(18),
        border: Border.all(color: AppColors.primaryGreen.withAlpha(80)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          // Minus button
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => provider.updateCartQuantity(productId, quantity - 1),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(7),
                bottomLeft: Radius.circular(7),
              ),
              child: const SizedBox(
                width: 36,
                height: 34,
                child: Icon(
                  Icons.remove_rounded,
                  size: 16,
                  color: AppColors.primaryGreen,
                ),
              ),
            ),
          ),
          // Quantity display
          Expanded(
            child: Text(
              '$quantity',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 13,
                color: AppColors.primaryGreen,
              ),
            ),
          ),
          // Plus button
          Material(
            color: AppColors.primaryGreen,
            borderRadius: const BorderRadius.only(
              topRight: Radius.circular(7),
              bottomRight: Radius.circular(7),
            ),
            child: InkWell(
              onTap: () => provider.updateCartQuantity(productId, quantity + 1),
              borderRadius: const BorderRadius.only(
                topRight: Radius.circular(7),
                bottomRight: Radius.circular(7),
              ),
              child: const SizedBox(
                width: 36,
                height: 34,
                child: Icon(Icons.add_rounded, size: 16, color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMiniCartBar() {
    return Consumer<OrderProvider>(
      builder: (context, provider, _) {
        if (provider.cartItems.isEmpty) return const SizedBox.shrink();

        final products = context.read<ProductProvider>().allProducts;
        final total = provider.getCartTotal(products);
        final count = provider.cartItems.values.fold(
          0,
          (sum, qty) => sum + qty,
        );

        return Container(
          width: double.infinity,
          padding: const EdgeInsets.all(AppConstants.defaultPadding),
          decoration: const BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: AppColors.shadow,
                blurRadius: 10,
                offset: Offset(0, -3),
              ),
            ],
          ),
          child: SafeArea(
            top: false,
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppColors.primaryGreen.withAlpha(26),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(
                    Icons.shopping_cart,
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
                        '$count item${count == 1 ? '' : 's'} in cart',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                      Text(
                        Formatters.currency(total),
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
                AppButton(
                  label: 'View Cart',
                  onPressed: () => _openCart(),
                  icon: Icons.arrow_forward,
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _openCart() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const CreateOrderScreen()));
  }

  void _addToOrder(Product product) {
    context.read<OrderProvider>().addToCart(product.id, 1);
    AppNotify.success(
      context,
      '${product.name} added',
      'Item added to cart. Tap to view cart.',
      onTap: _openCart,
    );
  }

  void _showPriceSlabSheet(Product product) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) => _PriceSlabSheet(product: product),
    );
  }
}

class _PriceSlabSheet extends StatefulWidget {
  final Product product;

  const _PriceSlabSheet({required this.product});

  @override
  State<_PriceSlabSheet> createState() => _PriceSlabSheetState();
}

class _PriceSlabSheetState extends State<_PriceSlabSheet> {
  int _selectedImageIndex = 0;

  @override
  Widget build(BuildContext context) {
    final images = widget.product.imageUrls.isNotEmpty
        ? widget.product.imageUrls
        : (widget.product.imageUrl != null &&
                  widget.product.imageUrl!.isNotEmpty
              ? [widget.product.imageUrl!]
              : <String>[]);

    return DraggableScrollableSheet(
      initialChildSize: 0.75,
      minChildSize: 0.4,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: const EdgeInsets.all(AppConstants.defaultPadding),
          child: ListView(
            controller: scrollController,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              if (images.isNotEmpty) ...[
                SizedBox(
                  height: 200,
                  child: PageView.builder(
                    itemCount: images.length,
                    onPageChanged: (index) {
                      setState(() {
                        _selectedImageIndex = index;
                      });
                    },
                    itemBuilder: (context, index) {
                      return Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: ProductImage(
                          imageUrl: images[index],
                          productName: widget.product.name,
                          width: double.infinity,
                          height: 200,
                          fit: BoxFit.contain,
                          backgroundColor: AppColors.backgroundLight,
                          borderRadius: 12,
                        ),
                      );
                    },
                  ),
                ),
                if (images.length > 1) ...[
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(
                      images.length,
                      (index) => Container(
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        width: _selectedImageIndex == index ? 16 : 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: _selectedImageIndex == index
                              ? AppColors.primaryGreen
                              : AppColors.border,
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 16),
              ],
              Text(
                widget.product.name,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${widget.product.categoryName} · ${widget.product.unitOfMeasure}',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
              if (widget.product.description != null &&
                  widget.product.description!.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(
                  widget.product.description!,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                    height: 1.4,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              const Text(
                'Price Slabs',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              if (widget.product.priceSlabs.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: Center(
                    child: Text(
                      'Standard price: ${Formatters.currency(widget.product.standardPrice)}',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                )
              else
                ...widget.product.priceSlabs.map(
                  (slab) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: AppColors.primaryGreen.withAlpha(26),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Icon(
                            Icons.inventory,
                            size: 16,
                            color: AppColors.primaryGreen,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                slab.displayLabel,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                  fontSize: 13,
                                ),
                              ),
                              if (slab.label != null)
                                Text(
                                  slab.label!,
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        Text(
                          Formatters.currency(slab.pricePerUnit),
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: AppColors.primaryGreen,
                          ),
                        ),
                        const SizedBox(width: 4),
                        const Text(
                          '/unit',
                          style: TextStyle(
                            fontSize: 11,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
