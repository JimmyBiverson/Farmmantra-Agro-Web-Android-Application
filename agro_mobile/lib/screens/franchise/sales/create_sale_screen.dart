import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/customer.dart';
import '../../../models/product.dart';
import '../../../providers/customer_provider.dart';
import '../../../providers/product_provider.dart';
import '../../../services/api/api_service.dart';
import '../../../widgets/common/app_button.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/app_text_field.dart';
import '../../../widgets/common/loading_view.dart';

class CreateSaleScreen extends StatefulWidget {
  const CreateSaleScreen({super.key});

  @override
  State<CreateSaleScreen> createState() => _CreateSaleScreenState();
}

class _CreateSaleScreenState extends State<CreateSaleScreen> {
  final _remarksController = TextEditingController();
  final _quantityController = TextEditingController(text: '1');
  Customer? _selectedCustomer;
  final List<SaleLineItem> _saleItems = [];
  bool _isSubmitting = false;

  /// 'farmer' or 'retailer' — follows the selected customer's type. Prices
  /// per tier are fixed by Farmmantra; the franchise cannot type a price.
  String _priceTier = 'farmer';
  String _paymentMethod = 'cash';

  static const _paymentMethods = {
    'cash': 'Cash',
    'mobile_money': 'Mobile Money',
    'bank_transfer': 'Bank Transfer',
    'credit': 'Credit (customer pays later)',
  };

  void _applyTier(String tier) {
    final products = context.read<ProductProvider>().products;
    setState(() {
      _priceTier = tier;
      for (var i = 0; i < _saleItems.length; i++) {
        final item = _saleItems[i];
        final product = products.where((p) => p.id == item.productId).firstOrNull;
        if (product != null) {
          _saleItems[i] = SaleLineItem(
            productId: item.productId,
            productName: item.productName,
            quantity: item.quantity,
            unitPrice: product.sellingPriceFor(tier),
          );
        }
      }
    });
  }

  @override
  void initState() {
    super.initState();
    context.read<CustomerProvider>().loadCustomers();
    context.read<ProductProvider>().loadProducts();
  }

  @override
  void dispose() {
    _remarksController.dispose();
    _quantityController.dispose();
    super.dispose();
  }

  double get _totalAmount =>
      _saleItems.fold(0, (sum, item) => sum + item.lineTotal);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Sale'),
      ),
      body: LoadingOverlay(
        isLoading: _isSubmitting,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppConstants.defaultPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildCustomerSelector(),
              const SizedBox(height: 12),
              _buildPricingOptions(),
              const SizedBox(height: 16),
              _buildProductSelection(),
              const SizedBox(height: 16),
              if (_saleItems.isNotEmpty) ...[
                _buildSaleItemsList(),
                const SizedBox(height: 16),
                _buildTotalSection(),
                const SizedBox(height: 12),
                _buildRemarksField(),
                const SizedBox(height: 16),
                _buildSubmitButton(),
              ],
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCustomerSelector() {
    return AppCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Customer',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 8),
          Consumer<CustomerProvider>(
            builder: (context, provider, _) {
              return InkWell(
                onTap: () => _showCustomerPicker(provider.customers),
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    border: Border.all(color: AppColors.border),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.person_outline,
                        color: _selectedCustomer != null
                            ? AppColors.primaryGreen
                            : AppColors.textLight,
                        size: 20,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _selectedCustomer != null
                            ? Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _selectedCustomer!.name,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                      fontSize: 14,
                                    ),
                                  ),
                                  Text(
                                    _selectedCustomer!.phone,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                ],
                              )
                            : const Text(
                                'Select a customer',
                                style: TextStyle(
                                  color: AppColors.textLight,
                                  fontSize: 14,
                                ),
                              ),
                      ),
                      const Icon(Icons.chevron_right, color: AppColors.textLight),
                    ],
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildPricingOptions() {
    return AppCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Selling price',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              ChoiceChip(
                label: const Text('Farmer price'),
                selected: _priceTier == 'farmer',
                onSelected: (_) => _applyTier('farmer'),
              ),
              ChoiceChip(
                label: const Text('Retail price'),
                selected: _priceTier == 'retailer',
                onSelected: (_) => _applyTier('retailer'),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'Prices are set by Farmmantra and cannot be changed.',
            style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _paymentMethod,
            decoration: const InputDecoration(labelText: 'Payment method'),
            items: _paymentMethods.entries
                .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                .toList(),
            onChanged: (v) => setState(() => _paymentMethod = v ?? 'cash'),
          ),
        ],
      ),
    );
  }

  Widget _buildProductSelection() {
    return AppCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Add Products',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 8),
          InkWell(
            onTap: _showProductPicker,
            borderRadius: BorderRadius.circular(8),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                border: Border.all(
                  color: AppColors.primaryGreen.withAlpha(128),
                  style: BorderStyle.solid,
                ),
                borderRadius: BorderRadius.circular(8),
                color: AppColors.primaryGreen.withAlpha(13),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.add, color: AppColors.primaryGreen, size: 20),
                  SizedBox(width: 8),
                  Text(
                    'Tap to add product',
                    style: TextStyle(
                      color: AppColors.primaryGreen,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSaleItemsList() {
    return AppCard(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Sale Items (${_saleItems.length})',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 8),
          ...List.generate(_saleItems.length, (index) {
            final item = _saleItems[index];
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.productName,
                          style: const TextStyle(
                            fontWeight: FontWeight.w500,
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          '${item.quantity} × ${Formatters.currency(item.unitPrice)}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    Formatters.currency(item.lineTotal),
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(width: 8),
                  InkWell(
                    onTap: () => setState(() => _saleItems.removeAt(index)),
                    child: const Icon(
                      Icons.close,
                      size: 16,
                      color: AppColors.error,
                    ),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildTotalSection() {
    return AppCard(
      color: AppColors.primaryGreen.withAlpha(13),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text(
            'Total',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          Text(
            Formatters.currency(_totalAmount),
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: AppColors.primaryGreen,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRemarksField() {
    return AppTextField(
      controller: _remarksController,
      label: 'Remarks (Optional)',
      hint: 'Any additional notes for this sale',
      prefixIcon: Icons.notes_outlined,
      maxLines: 2,
    );
  }

  Widget _buildSubmitButton() {
    return SizedBox(
      width: double.infinity,
      child: AppButton(
        label: 'Complete Sale',
        isLoading: _isSubmitting,
        onPressed: _selectedCustomer == null || _saleItems.isEmpty
            ? null
            : _submitSale,
        icon: Icons.check_circle_outline,
      ),
    );
  }

  void _showCustomerPicker(List<Customer> customers) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.5,
          minChildSize: 0.3,
          maxChildSize: 0.8,
          expand: false,
          builder: (context, scrollController) {
            return Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(AppConstants.defaultPadding),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Select Customer',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: customers.isEmpty
                      ? const Center(
                          child: Text(
                            'No customers available',
                            style: TextStyle(color: AppColors.textSecondary),
                          ),
                        )
                      : ListView.builder(
                          controller: scrollController,
                          itemCount: customers.length,
                          itemBuilder: (context, index) {
                            final customer = customers[index];
                            return ListTile(
                              leading: CircleAvatar(
                                backgroundColor:
                                    AppColors.primaryGreen.withAlpha(26),
                                child: Text(
                                  customer.name[0].toUpperCase(),
                                  style: const TextStyle(
                                    color: AppColors.primaryGreen,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                              title: Text(customer.name),
                              subtitle: Text('${customer.typeLabel} · ${customer.phone}'),
                              onTap: () {
                                setState(() => _selectedCustomer = customer);
                                _applyTier(customer.customerType == 'retailer' ? 'retailer' : 'farmer');
                                Navigator.pop(context);
                              },
                            );
                          },
                        ),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _showProductPicker() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.6,
          minChildSize: 0.3,
          maxChildSize: 0.85,
          expand: false,
          builder: (context, scrollController) {
            return Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(AppConstants.defaultPadding),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Select Product',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(context),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: Consumer<ProductProvider>(
                    builder: (context, provider, _) {
                      if (provider.products.isEmpty) {
                        return const Center(
                          child: Text('No products available'),
                        );
                      }

                      return ListView.builder(
                        controller: scrollController,
                        itemCount: provider.products.length,
                        itemBuilder: (context, index) {
                          final product = provider.products[index];
                          return ListTile(
                            title: Text(
                              product.name,
                              style: const TextStyle(
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            subtitle: Text(
                              '${product.categoryName} · ${Formatters.currency(product.sellingPriceFor(_priceTier))}',
                            ),
                            trailing: const Icon(
                              Icons.add_circle_outline,
                              color: AppColors.primaryGreen,
                            ),
                            onTap: () {
                              Navigator.pop(context);
                              _showQuantityDialog(product);
                            },
                          );
                        },
                      );
                    },
                  ),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _showQuantityDialog(Product product) {
    _quantityController.text = '1';

    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: Text(product.name),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '${_priceTier == 'retailer' ? 'Retail' : 'Farmer'} price: ${Formatters.currency(product.sellingPriceFor(_priceTier))}',
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _quantityController,
                label: 'Quantity',
                keyboardType: TextInputType.number,
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
                final qty = int.tryParse(_quantityController.text) ?? 0;
                if (qty > 0) {
                  final price = product.sellingPriceFor(_priceTier);
                  setState(() {
                    _saleItems.add(SaleLineItem(
                      productId: product.id,
                      productName: product.name,
                      quantity: qty,
                      unitPrice: price,
                    ));
                  });
                  Navigator.pop(context);
                }
              },
              child: const Text('Add'),
            ),
          ],
        );
      },
    );
  }

  Future<void> _submitSale() async {
    if (_selectedCustomer == null || _saleItems.isEmpty) return;

    setState(() => _isSubmitting = true);

    try {
      final apiService = context.read<ApiService>();
      await apiService.createSale({
        'customer_id': _selectedCustomer!.id,
        'price_tier': _priceTier,
        'payment_method': _paymentMethod,
        'items': _saleItems
            .map((item) => {
                  'product_id': item.productId,
                  'quantity': item.quantity,
                  'unit_price': item.unitPrice,
                  'total_price': item.lineTotal,
                })
            .toList(),
        'total_amount': _totalAmount,
        'notes': _remarksController.text.trim(),
      });

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Sale completed for ${Formatters.currency(_totalAmount)}',
          ),
          backgroundColor: AppColors.primaryGreen,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
      );
      Navigator.of(context).pop();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to submit sale: $e'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
      );
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }
}

class SaleLineItem {
  final String productId;
  final String productName;
  final int quantity;
  final double unitPrice;

  const SaleLineItem({
    required this.productId,
    required this.productName,
    required this.quantity,
    required this.unitPrice,
  });

  double get lineTotal => quantity * unitPrice;
}
