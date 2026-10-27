import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../providers/order_provider.dart';
import '../../../providers/product_provider.dart';
import '../../../widgets/common/app_button.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';
import '../../../widgets/common/product_image.dart';
import '../../../services/notification_service.dart';
import 'order_detail_screen.dart';

class CreateOrderScreen extends StatefulWidget {
  const CreateOrderScreen({super.key});

  @override
  State<CreateOrderScreen> createState() => _CreateOrderScreenState();
}

class _CreateOrderScreenState extends State<CreateOrderScreen> {
  bool _isSubmitting = false;

  /// 'credit' = pay later within the credit limit; 'cash' = pay (mobile
  /// money / bank) before the warehouse dispatches.
  String _paymentTerms = 'credit';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Order'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () => Navigator.of(context).pop(),
            tooltip: 'Add Products',
          ),
        ],
      ),
      body: LoadingOverlay(
        isLoading: _isSubmitting,
        child: Column(
          children: [
            Expanded(child: _buildCartBody()),
            _buildOrderSummary(),
          ],
        ),
      ),
    );
  }

  Widget _buildCartBody() {
    return Consumer<OrderProvider>(
      builder: (context, provider, _) {
        final entries = provider.cartItems.entries.toList();

        if (entries.isEmpty) {
          return const EmptyView(
            message: 'Your cart is empty. Browse products to add items.',
            title: 'No Items in Cart',
            icon: Icons.shopping_cart_outlined,
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.all(AppConstants.defaultPadding),
          itemCount: entries.length,
          separatorBuilder: (context, index) => const SizedBox(height: 8),
          itemBuilder: (context, index) =>
              _buildCartItemCard(context, entries[index]),
        );
      },
    );
  }

  Widget _buildCartItemCard(BuildContext context, MapEntry<String, int> cartEntry) {
    final productId = cartEntry.key;
    final quantity = cartEntry.value;
    final products = context.read<ProductProvider>().allProducts;
    final product = products.where((p) => p.id == productId).firstOrNull;
    final applicableSlab = product?.getApplicableSlab(quantity);
    final unitPrice = product?.getPriceForQuantity(quantity) ?? 0;
    final totalPrice = unitPrice * quantity;
    final productName = product?.name ?? 'Unknown Product';
    final categoryName = product?.categoryName ?? '';

    return AppCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ProductImage(
                imageUrl: product?.imageUrl,
                productName: productName,
                width: 56,
                height: 56,
                borderRadius: 8,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      productName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      categoryName,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                icon: const Icon(
                  Icons.delete_outline,
                  size: 20,
                  color: AppColors.error,
                ),
                onPressed: () => context.read<OrderProvider>().removeFromCart(productId),
              ),
            ],
          ),
          if (applicableSlab != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.primaryGreen.withAlpha(26),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                'Slab: ${applicableSlab.displayLabel} @ ${Formatters.currency(applicableSlab.pricePerUnit)}/unit',
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.primaryGreen,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              _buildQuantitySelector(context, productId, quantity),
              const Spacer(),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  if (applicableSlab != null)
                    Text(
                      Formatters.currency(product?.standardPrice ?? 0),
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.textLight,
                        decoration: TextDecoration.lineThrough,
                      ),
                    ),
                  Text(
                    Formatters.currency(totalPrice),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                      color: AppColors.primaryGreen,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildQuantitySelector(
      BuildContext context, String productId, int currentQuantity) {
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.border),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          InkWell(
            onTap: currentQuantity > 1
                ? () => context.read<OrderProvider>().updateCartQuantity(productId, currentQuantity - 1)
                : null,
            child: Container(
              padding: const EdgeInsets.all(6),
              child: Icon(
                Icons.remove,
                size: 16,
                color: currentQuantity > 1
                    ? AppColors.primaryGreen
                    : AppColors.textLight,
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              '$currentQuantity',
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 14,
              ),
            ),
          ),
          InkWell(
            onTap: () =>
                context.read<OrderProvider>().updateCartQuantity(productId, currentQuantity + 1),
            child: Container(
              padding: const EdgeInsets.all(6),
              child: const Icon(
                Icons.add,
                size: 16,
                color: AppColors.primaryGreen,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderSummary() {
    return Consumer<OrderProvider>(
      builder: (context, provider, _) {
        final items = provider.cartItems;
      final products = context.read<ProductProvider>().allProducts;
        final total = provider.getCartTotal(products);

        return Container(
          padding: const EdgeInsets.all(AppConstants.defaultPadding),
          decoration: const BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: AppColors.shadow,
                blurRadius: 8,
                offset: Offset(0, -2),
              ),
            ],
          ),
          child: SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Total (${items.length} items)',
                      style: const TextStyle(
                        fontSize: 14,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    Text(
                      Formatters.currency(total),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Text(
                      'Payment:',
                      style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                    ),
                    const SizedBox(width: 8),
                    ChoiceChip(
                      label: const Text('Credit'),
                      selected: _paymentTerms == 'credit',
                      onSelected: (_) => setState(() => _paymentTerms = 'credit'),
                    ),
                    const SizedBox(width: 8),
                    ChoiceChip(
                      label: const Text('Cash'),
                      selected: _paymentTerms == 'cash',
                      onSelected: (_) => setState(() => _paymentTerms = 'cash'),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: AppButton(
                    label: 'Submit Order',
                    isLoading: _isSubmitting,
                    onPressed: items.isEmpty ? null : _submitOrder,
                    icon: Icons.send,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _submitOrder() async {
    setState(() => _isSubmitting = true);

    try {
      final provider = context.read<OrderProvider>();
      final products = context.read<ProductProvider>().products;
      final cartItems = provider.getCartItems();
      final items = cartItems.map((entry) {
        final product = products.where((p) => p.id == entry['product_id']).firstOrNull;
        final qty = (entry['quantity'] as num).toInt();
        final unitPrice = product?.getPriceForQuantity(qty) ?? 0;
        return {
          'product_id': entry['product_id'],
          'quantity': qty,
          'unit_price': unitPrice,
          'total_price': unitPrice * qty,
          'product_name': product?.name ?? '',
          'category_name': product?.categoryName ?? '',
        };
      }).toList();
      final totalAmount = provider.getCartTotal(products);

      final success = await provider.createOrder({
        'items': items,
        'total_amount': totalAmount,
        'payment_terms': _paymentTerms,
      });
      if (!mounted) return;

      if (success) {
        final created = provider.lastCreatedOrder;
        final navigator = Navigator.of(context);
        AppNotify.orderUpdate(
          context,
          'Order placed! We\u2019ll review and confirm it shortly.',
        );
        navigator.pop();
        if (created != null) {
          navigator.push(
            MaterialPageRoute(
              builder: (_) => OrderDetailScreen(orderId: created.id),
            ),
          );
        }
      } else {
        AppNotify.error(
          context,
          'Order Failed',
          provider.error ?? 'Failed to submit order. Please try again.',
        );
      }
    } catch (e) {
      if (!mounted) return;
      AppNotify.error(
        context,
        'Order Error',
        'Something went wrong: ${e.toString().replaceFirst('Exception: ', '')}',
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }
}
