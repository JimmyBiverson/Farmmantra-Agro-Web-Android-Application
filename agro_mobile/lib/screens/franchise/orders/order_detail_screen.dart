import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/enums/app_enums.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/order.dart';
import '../../../providers/order_provider.dart';
import '../../../providers/product_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';
import '../../../widgets/common/product_image.dart';
import '../../../widgets/common/status_badge.dart';
import '../payments/payment_screen.dart';
import '../../../services/api/api_service.dart';
import '../../../services/print_service.dart';
import '../../shared/terms/terms_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  final String orderId;

  const OrderDetailScreen({super.key, required this.orderId});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  static const bool _isConfirmingDelivery = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OrderProvider>().loadOrder(widget.orderId);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Order ${widget.orderId}'),
        actions: [
          IconButton(
            tooltip: 'Print order',
            icon: const Icon(Icons.print_outlined),
            onPressed: () => PrintService.open(
              context,
              context.read<ApiService>(),
              'order',
              widget.orderId,
            ),
          ),
          IconButton(
            tooltip: 'Print delivery note',
            icon: const Icon(Icons.local_shipping_outlined),
            onPressed: () => PrintService.open(
              context,
              context.read<ApiService>(),
              'delivery-note',
              widget.orderId,
            ),
          ),
        ],
      ),
      body: Consumer<OrderProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading) {
            return const Center(
              child: CircularProgressIndicator(color: AppColors.primaryGreen),
            );
          }

          if (provider.error != null) {
            return ErrorView(
              message: provider.error!,
              onRetry: () => provider.loadOrder(widget.orderId),
            );
          }

          final order = provider.selectedOrder;
          if (order == null) {
            return const ErrorView(message: 'Order not found');
          }

          return LoadingOverlay(
            isLoading: _isConfirmingDelivery,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(AppConstants.defaultPadding),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildOrderHeader(order),
                  const SizedBox(height: 16),
                  _buildOrderTimeline(order),
                  const SizedBox(height: 16),
                  _buildOrderItems(order),
                  const SizedBox(height: 16),
                  _buildOrderTotal(order),
                  const SizedBox(height: 16),
                  _buildTermsAndBalance(order),
                  if (order.awaitingFranchiseAcceptance) ...[
                    const SizedBox(height: 16),
                    _buildAcceptDeliveryCard(order, provider),
                  ],
                  if (order.statusEnum == OrderStatus.approved &&
                      order.expectedDeliveryDate != null) ...[
                    const SizedBox(height: 16),
                    _buildDeliveryInfo(order),
                  ],
                  if (order.statusEnum == OrderStatus.approved) ...[
                    const SizedBox(height: 16),
                    _buildPaymentStatus(order),
                  ],
                  if (order.statusEnum == OrderStatus.approved &&
                      order.isPaymentReady) ...[
                    const SizedBox(height: 16),
                    _buildReadyForDeliveryInfo(order),
                  ],
                  if (order.statusEnum == OrderStatus.declined &&
                      order.declineReason != null) ...[
                    const SizedBox(height: 16),
                    _buildDeclineInfo(order),
                  ],
                  if (order.statusEnum == OrderStatus.adjusted) ...[
                    const SizedBox(height: 16),
                    _buildAdjustmentInfo(order),
                  ],
                  if (order.statusEnum == OrderStatus.delivered &&
                      !order.awaitingFranchiseAcceptance) ...[
                    const SizedBox(height: 16),
                    _buildDeliveryConfirmation(order),
                  ],
                  const SizedBox(height: 24),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildOrderHeader(Order order) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                order.id,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  StatusBadge.fromOrderStatus(order.status),
                  if (order.statusEnum == OrderStatus.approved) ...[
                    const SizedBox(height: 4),
                    StatusBadge.fromDeliveryStatus(order.deliveryStatusEnum),
                  ],
                ],
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(
                Icons.calendar_today,
                size: 14,
                color: AppColors.textSecondary,
              ),
              const SizedBox(width: 6),
              Text(
                Formatters.dateTime(order.createdAt),
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(
                Icons.person_outline,
                size: 14,
                color: AppColors.textSecondary,
              ),
              const SizedBox(width: 6),
              Text(
                order.franchiseName,
                style: const TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildOrderTimeline(Order order) {
    final steps = _getTimelineSteps(order);

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Progress',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 16),
          ...List.generate(steps.length, (index) {
            final step = steps[index];
            final isLast = index == steps.length - 1;
            return _buildTimelineStep(
              title: step['title'] as String,
              subtitle: step['subtitle'] as String?,
              icon: step['icon'] as IconData,
              isActive: step['isActive'] as bool,
              isLast: isLast,
            );
          }),
        ],
      ),
    );
  }

  Widget _buildTimelineStep({
    required String title,
    String? subtitle,
    required IconData icon,
    required bool isActive,
    required bool isLast,
  }) {
    final color = isActive ? AppColors.primaryGreen : AppColors.textLight;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: isActive
                    ? AppColors.primaryGreen.withAlpha(26)
                    : AppColors.backgroundLight,
                shape: BoxShape.circle,
                border: Border.all(color: color, width: 2),
              ),
              child: Icon(icon, size: 16, color: color),
            ),
            if (!isLast)
              Container(
                width: 2,
                height: 32,
                color: isActive ? AppColors.primaryGreen : AppColors.border,
              ),
          ],
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    color: isActive
                        ? AppColors.textPrimary
                        : AppColors.textSecondary,
                  ),
                ),
                if (subtitle != null)
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textLight,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildOrderItems(Order order) {
    final products = context.read<ProductProvider>().allProducts;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Items',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),
          ...order.items.map((item) {
            final product = products
                .where((p) => p.id == item.productId)
                .firstOrNull;
            return _buildOrderItemRow(item, product?.imageUrl);
          }),
        ],
      ),
    );
  }

  Widget _buildOrderItemRow(OrderItem item, String? imageUrl) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          ProductImage(
            imageUrl: imageUrl,
            productName: item.productName,
            width: 44,
            height: 44,
            borderRadius: 8,
          ),
          const SizedBox(width: 10),
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
                const SizedBox(height: 2),
                Text(
                  item.categoryName,
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            flex: 2,
            child: Text(
              '${item.quantity} × ${Formatters.currency(item.unitPrice)}',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
              textAlign: TextAlign.center,
            ),
          ),
          Expanded(
            flex: 2,
            child: Text(
              Formatters.currency(item.totalPrice),
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
              textAlign: TextAlign.right,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderTotal(Order order) {
    final displayAmount = order.adjustedAmount ?? order.totalAmount;
    final hasAdjustment =
        order.adjustedAmount != null &&
        order.adjustedAmount != order.totalAmount;

    return AppCard(
      child: Column(
        children: [
          if (hasAdjustment) ...[
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Original Total',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  Formatters.currency(order.totalAmount),
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                    decoration: TextDecoration.lineThrough,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
          ],
          if (order.taxAmount > 0) ...[
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Subtotal (excl. tax)',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  Formatters.currency(order.totalWithoutTax),
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Tax',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  Formatters.currency(order.taxAmount),
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
            const Divider(height: 16),
          ],
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                hasAdjustment ? 'Adjusted Total' : 'Total',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              Text(
                Formatters.currency(displayAmount),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryGreen,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentStatus(Order order) {
    final needsPayment =
        !order.isPaymentReady &&
        !order.isOutForDelivery &&
        order.statusEnum != OrderStatus.delivered;

    if (!needsPayment) {
      return AppCard(
        color: AppColors.success.withAlpha(13),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              const Icon(Icons.verified, color: AppColors.success, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Payment Verified',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: AppColors.success,
                      ),
                    ),
                    if (order.financeVerifiedAt != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        'Verified on ${Formatters.dateTime(order.financeVerifiedAt)}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      );
    }

    return AppCard(
      color: AppColors.warning.withAlpha(13),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.payments_outlined,
                  color: AppColors.warning,
                  size: 22,
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Text(
                    'Payment Required',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.warning,
                    ),
                  ),
                ),
                StatusBadge.fromDeliveryStatus(order.deliveryStatusEnum),
              ],
            ),
            const SizedBox(height: 10),
            const Text(
              'This order is approved. Submit a payment to get it verified '
              'by Finance and ready for delivery.',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: () => _continueToPayment(order),
                icon: const Icon(Icons.account_balance_wallet_outlined),
                label: const Text('Continue to Payment'),
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primaryGreen,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildReadyForDeliveryInfo(Order order) {
    return AppCard(
      color: AppColors.primaryGreen.withAlpha(13),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.verified_outlined,
                  color: AppColors.primaryGreen,
                  size: 22,
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Text(
                    'Ready for Delivery',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.primaryGreen,
                    ),
                  ),
                ),
                StatusBadge.fromDeliveryStatus(order.deliveryStatusEnum),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              order.isOutForDelivery
                  ? 'Your order is out for delivery. Please be ready to receive it.'
                  : 'Your payment was accepted. The order will be dispatched shortly.',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _continueToPayment(Order order) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => Scaffold(
          appBar: AppBar(title: const Text('Pay for Order')),
          body: PaymentScreen(preSelectedOrderIds: [order.id]),
        ),
      ),
    );
  }

  Widget _buildDeliveryInfo(Order order) {
    return AppCard(
      color: AppColors.primaryGreen.withAlpha(13),
      child: Row(
        children: [
          const Icon(
            Icons.local_shipping_outlined,
            color: AppColors.primaryGreen,
            size: 24,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Expected Delivery',
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  Formatters.date(order.expectedDeliveryDate),
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primaryGreen,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDeclineInfo(Order order) {
    return AppCard(
      color: AppColors.error.withAlpha(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.cancel_outlined,
                color: AppColors.error,
                size: 20,
              ),
              const SizedBox(width: 8),
              const Text(
                'Decline Reason',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.error,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            order.declineReason!,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAdjustmentInfo(Order order) {
    return AppCard(
      color: AppColors.statusAdjusted.withAlpha(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.edit_note,
                color: AppColors.statusAdjusted,
                size: 20,
              ),
              const SizedBox(width: 8),
              const Text(
                'Adjustment Notes',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.statusAdjusted,
                ),
              ),
            ],
          ),
          if (order.adjustmentNotes != null) ...[
            const SizedBox(height: 8),
            Text(
              order.adjustmentNotes!,
              style: const TextStyle(
                fontSize: 13,
                color: AppColors.textSecondary,
              ),
            ),
          ],
          if (order.adjustedAmount != null) ...[
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Adjusted Amount',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  Formatters.currency(order.adjustedAmount),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.statusAdjusted,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildTermsAndBalance(Order order) {
    Widget row(String label, String value, {Color? color}) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 13, color: AppColors.textSecondary)),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: color ?? AppColors.textPrimary),
            ),
          ),
        ],
      ),
    );
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          row('Payment terms', order.isCashOrder ? 'Cash — pay before dispatch' : 'Credit'),
          row('Paid', Formatters.currency(order.amountPaid), color: AppColors.success),
          row('Balance due', Formatters.currency(order.balanceDue), color: order.balanceDue > 0 ? AppColors.warning : AppColors.success),
          if (order.dispatchMeansLabel != null)
            row('Dispatched by', '${order.dispatchMeansLabel}${(order.dispatchVehicle ?? '').isNotEmpty ? ' · ${order.dispatchVehicle}' : ''}'),
          if ((order.dispatchRemarks ?? '').isNotEmpty) row('Dispatch remarks', order.dispatchRemarks!),
          if (order.deliverySignature != null) row('Receipt signature', order.deliverySignature!),
        ],
      ),
    );
  }

  Widget _buildAcceptDeliveryCard(Order order, OrderProvider provider) {
    return AppCard(
      color: AppColors.primaryGreen.withAlpha(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.inventory_2_outlined, color: AppColors.primaryGreen),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Your order has been dispatched',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.primaryGreen),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'When the goods arrive, count them, then accept the delivery. '
            'Your stock is updated and you are charged only for what you received.',
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: provider.isLoading ? null : () => _showAcceptDeliveryDialog(order, provider),
              icon: const Icon(Icons.fact_check_outlined),
              label: const Text('Accept delivery'),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _showAcceptDeliveryDialog(Order order, OrderProvider provider) async {
    final controllers = <String, TextEditingController>{
      for (final line in order.receiptLines)
        line.id: TextEditingController(
          text: line.dispatchedQuantity == line.dispatchedQuantity.roundToDouble()
              ? line.dispatchedQuantity.toInt().toString()
              : line.dispatchedQuantity.toString(),
        ),
    };
    final notesController = TextEditingController();
    bool accepted = false;
    String? error;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (dialogContext, setDialogState) => AlertDialog(
          title: const Text('Accept delivery'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (order.receiptLines.isEmpty)
                  const Text('All dispatched items will be recorded as received.')
                else
                  ...order.receiptLines.map(
                    (line) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: TextField(
                        controller: controllers[line.id],
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: InputDecoration(
                          labelText: '${line.productName} received',
                          helperText: 'Dispatched: ${line.dispatchedQuantity}',
                        ),
                      ),
                    ),
                  ),
                TextField(
                  controller: notesController,
                  decoration: const InputDecoration(labelText: 'Shortage / damage notes (optional)'),
                  maxLines: 2,
                ),
                const SizedBox(height: 8),
                TextButton.icon(
                  onPressed: () => Navigator.of(dialogContext).push(
                    MaterialPageRoute(builder: (_) => const TermsScreen(readOnly: true)),
                  ),
                  icon: const Icon(Icons.description_outlined),
                  label: const Text('Read terms & conditions'),
                ),
                CheckboxListTile(
                  value: accepted,
                  onChanged: (v) => setDialogState(() => accepted = v ?? false),
                  controlAffinity: ListTileControlAffinity.leading,
                  contentPadding: EdgeInsets.zero,
                  title: const Text(
                    'I have counted the goods, read the terms and conditions and accept this delivery.',
                    style: TextStyle(fontSize: 13),
                  ),
                ),
                if (error != null)
                  Text(error!, style: const TextStyle(color: AppColors.error, fontSize: 12)),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
            ElevatedButton(
              onPressed: !accepted
                  ? null
                  : () {
                      for (final line in order.receiptLines) {
                        final v = double.tryParse(controllers[line.id]!.text.trim());
                        if (v == null || v < 0 || v > line.dispatchedQuantity) {
                          setDialogState(() => error = 'Enter 0 – ${line.dispatchedQuantity} for ${line.productName}.');
                          return;
                        }
                      }
                      Navigator.pop(dialogContext, true);
                    },
              child: const Text('Accept & sign'),
            ),
          ],
        ),
      ),
    );

    final quantities = <String, double>{
      for (final line in order.receiptLines)
        line.id: double.tryParse(controllers[line.id]!.text.trim()) ?? line.dispatchedQuantity,
    };
    final notes = notesController.text.trim();
    for (final c in controllers.values) {
      c.dispose();
    }
    notesController.dispose();
    if (confirmed != true || !mounted) return;

    final success = await provider.confirmDelivery(
      order.id,
      notes: notes,
      receivedQuantities: quantities.isEmpty ? null : quantities,
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(success
            ? 'Delivery accepted. Your stock has been updated.'
            : (provider.error ?? 'Could not accept the delivery.')),
        backgroundColor: success ? AppColors.success : AppColors.error,
      ),
    );
    if (success) {
      provider.loadOrder(order.id);
    }
  }

  Widget _buildDeliveryConfirmation(Order order) {
    return AppCard(
      color: AppColors.statusDelivered.withAlpha(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.check_circle_outline,
                color: AppColors.statusDelivered,
                size: 20,
              ),
              const SizedBox(width: 8),
              Text(
                'Delivered on ${Formatters.date(order.deliveredAt)}',
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.statusDelivered,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  List<Map<String, dynamic>> _getTimelineSteps(Order order) {
    final steps = <Map<String, dynamic>>[
      {
        'title': 'Order Placed',
        'subtitle': Formatters.dateTime(order.createdAt),
        'icon': Icons.shopping_cart_outlined,
        'isActive': true,
      },
      {
        'title': 'Under Review',
        'subtitle': order.statusEnum != OrderStatus.pending
            ? 'Reviewed'
            : 'Pending',
        'icon': Icons.rate_review_outlined,
        'isActive': order.statusEnum != OrderStatus.pending,
      },
    ];

    if (order.statusEnum == OrderStatus.declined) {
      steps.add({
        'title': 'Declined',
        'subtitle': 'Order was declined',
        'icon': Icons.cancel_outlined,
        'isActive': true,
      });
    } else {
      steps.add({
        'title': 'Approved',
        'subtitle': order.expectedDeliveryDate != null
            ? 'Approved · Expected delivery ${Formatters.date(order.expectedDeliveryDate)}'
            : 'Order approved',
        'icon': Icons.check_circle_outline,
        'isActive': order.statusEnum.index >= OrderStatus.approved.index,
      });

      steps.add({
        'title': 'Payment',
        'subtitle': order.isPaymentReady
            ? 'Payment verified'
            : 'Awaiting payment verification',
        'icon': Icons.account_balance_wallet_outlined,
        'isActive': order.isPaymentComplete,
      });

      steps.add({
        'title': 'Dispatch',
        'subtitle': order.isOutForDelivery
            ? 'Out for delivery'
            : order.isPaymentReady
            ? 'Ready for dispatch'
            : 'Pending',
        'icon': Icons.local_shipping_outlined,
        'isActive': order.isDispatchComplete,
      });

      if (order.statusEnum == OrderStatus.adjusted) {
        steps.add({
          'title': 'Adjusted',
          'subtitle': 'Quantities adjusted',
          'icon': Icons.edit_note,
          'isActive': true,
        });
      }

      steps.add({
        'title': 'Delivered',
        'subtitle': order.deliveredAt != null
            ? Formatters.date(order.deliveredAt)
            : 'Pending delivery',
        'icon': Icons.check_circle_outline,
        'isActive': order.statusEnum == OrderStatus.delivered,
      });
    }

    return steps;
  }
}
