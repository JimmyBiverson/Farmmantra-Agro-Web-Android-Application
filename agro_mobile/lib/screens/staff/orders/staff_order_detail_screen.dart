import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/enums/app_enums.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/order.dart';
import '../../../providers/auth_provider.dart';
import '../../../providers/order_provider.dart';
import '../../../services/api/api_service.dart';
import '../../../services/print_service.dart';
import '../../../providers/payment_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/app_button.dart';
import '../../../widgets/common/app_text_field.dart';
import '../../../widgets/common/status_badge.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';

import '../../../widgets/common/product_image.dart';

class StaffOrderDetailScreen extends StatefulWidget {
  final String orderId;

  const StaffOrderDetailScreen({super.key, required this.orderId});

  @override
  State<StaffOrderDetailScreen> createState() => _StaffOrderDetailScreenState();
}

class _StaffOrderDetailScreenState extends State<StaffOrderDetailScreen> {
  Map<String, double> _adjustedQuantities = {};

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
        title: const Text('Order Details'),
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
        ],
      ),
      body: Consumer<OrderProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading) {
            return const LoadingView();
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

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildFranchiseHeader(order),
                const SizedBox(height: 16),
                _buildOrderItems(order),
                const SizedBox(height: 16),
                _buildFinancePaymentDetails(order),
                const SizedBox(height: 16),
                _buildOrderTotal(order),
                const SizedBox(height: 16),
                _buildStatusTimeline(order),
                const SizedBox(height: 16),
                _buildTraceability(order),
                const SizedBox(height: 16),
                _buildActionSection(order, provider),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildFranchiseHeader(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  order.franchiseName,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    StatusBadge.fromOrderStatus(order.status),
                    if (order.statusEnum == OrderStatus.approved ||
                        order.statusEnum == OrderStatus.delivered) ...[
                      const SizedBox(height: 4),
                      StatusBadge.fromDeliveryStatus(order.deliveryStatusEnum),
                    ],
                  ],
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Franchise ID: ${order.franchiseId}',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 4),
            Text(
              'Order #${order.id}',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 4),
            Text(
              'Placed on ${Formatters.dateTime(order.createdAt)}',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderItems(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Order Items',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            ...order.items.map((item) => _buildOrderItem(item)),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderItem(OrderItem item) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        children: [
          Row(
            children: [
              ProductImage(
                imageUrl: item.imageUrl,
                productName: item.productName,
                width: 52,
                height: 52,
                borderRadius: 8,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.productName,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.quantity} x ${Formatters.currency(item.unitPrice)}',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    if (item.taxRate > 0) ...[
                      const SizedBox(height: 2),
                      Text(
                        'Base ${Formatters.currency(item.baseUnitPrice)} + '
                        '${Formatters.currency(item.unitPrice - item.baseUnitPrice)} tax '
                        '(${Formatters.toDouble(item.taxRate)}%)',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.textLight,
                        ),
                      ),
                    ],
                    if (item.adjustedQuantity != null) ...[
                      const SizedBox(height: 2),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.warning.withAlpha(30),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          item.adjustedQuantity! == 0
                              ? 'Out of Stock / Rejected'
                              : 'Adjusted: ${item.adjustedQuantity} qty',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: item.adjustedQuantity! == 0
                                ? AppColors.error
                                : AppColors.warning,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Text(
                Formatters.currency(item.totalPrice),
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.bold),
              ),
            ],
          ),
          const Divider(height: 24),
        ],
      ),
    );
  }

  Widget _buildOrderTotal(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            if (order.taxAmount > 0) ...[
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Subtotal',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                  Text(
                    Formatters.currency(order.totalWithoutTax),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Tax',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                  Text(
                    Formatters.currency(order.taxAmount),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              ),
              const Divider(height: 16),
            ],
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Total Amount',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  Formatters.currency(order.displayedAmount),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.primaryGreen,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusTimeline(Order order) {
    final isDeliveryDeclined =
        order.deliveryStatusEnum == DeliveryStatus.declined;
    final steps = [
      _TimelineStep('Placed', Icons.shopping_cart, true),
      _TimelineStep(
        'Approved',
        Icons.check_circle,
        order.statusEnum != OrderStatus.pending,
      ),
      _TimelineStep(
        'Payment Accepted',
        Icons.verified,
        order.isPaymentComplete,
      ),
      _TimelineStep(
        'Out for Delivery',
        Icons.local_shipping,
        order.isDispatchComplete,
      ),
      _TimelineStep(
        'Delivered',
        Icons.assignment_turned_in,
        order.isFullyCompleted,
      ),
    ];

    if (order.statusEnum == OrderStatus.declined) {
      steps[1] = _TimelineStep('Declined', Icons.cancel, true);
    }

    if (isDeliveryDeclined) {
      steps[3] = _TimelineStep('Declined', Icons.cancel, true);
    }

    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Order Status',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            Column(
              children: List.generate(steps.length, (index) {
                final step = steps[index];
                final isLast = index == steps.length - 1;
                return _buildTimelineStep(step, isLast: isLast);
              }),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTimelineStep(_TimelineStep step, {bool isLast = false}) {
    final color = step.isActive ? AppColors.primaryGreen : AppColors.divider;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Column(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                child: Icon(
                  step.icon,
                  color: step.isActive ? Colors.white : AppColors.textSecondary,
                  size: 18,
                ),
              ),
              if (!isLast) Expanded(child: Container(width: 2, color: color)),
            ],
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 8, bottom: isLast ? 0 : 24),
              child: Text(
                step.label,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: step.isActive
                      ? AppColors.textPrimary
                      : AppColors.textSecondary,
                  fontWeight: step.isActive
                      ? FontWeight.bold
                      : FontWeight.normal,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Payment terms, dispatch details and the digital signature numbers that
  /// record exactly who confirmed, dispatched and received the order.
  Widget _buildTraceability(Order order) {
    final rows = <MapEntry<String, String>>[
      MapEntry('Payment terms', order.isCashOrder ? 'Cash (pay before dispatch)' : 'Credit'),
      if (order.dispatchMeansLabel != null)
        MapEntry(
          'Dispatched by',
          '${order.dispatchMeansLabel}${order.dispatchVehicle != null && order.dispatchVehicle!.isNotEmpty ? ' · ${order.dispatchVehicle}' : ''}',
        ),
      if (order.dispatchRemarks != null && order.dispatchRemarks!.isNotEmpty)
        MapEntry('Dispatch remarks', order.dispatchRemarks!),
      if (order.approvalSignature != null) MapEntry('Approval signature', order.approvalSignature!),
      if (order.dispatchSignature != null) MapEntry('Dispatch signature', order.dispatchSignature!),
      if (order.deliverySignature != null) MapEntry('Receipt signature', order.deliverySignature!),
    ];
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Traceability',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            ...rows.map((r) => _buildDetailRow(r.key, r.value)),
          ],
        ),
      ),
    );
  }

  bool get _canManage =>
      Provider.of<AuthProvider>(context, listen: false).userRole?.canManageOrders ??
      false;

  Widget _buildMonitoringInfo(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            const Icon(Icons.visibility_outlined, color: AppColors.info),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Monitoring only. Orders are accepted and dispatched by the '
                'Warehouse. Raise a support ticket if something needs attention.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionSection(Order order, OrderProvider provider) {
    if (!_canManage &&
        (order.statusEnum == OrderStatus.pending ||
            order.statusEnum == OrderStatus.approved)) {
      return _buildMonitoringInfo(order);
    }
    switch (order.statusEnum) {
      case OrderStatus.pending:
        return _buildPendingActions(order, provider);
      case OrderStatus.approved:
        return _buildApprovedSection(order, provider);
      case OrderStatus.declined:
        return _buildDeclinedInfo(order);
      case OrderStatus.delivered:
        return _buildDeliveredInfo(order);
      default:
        return const SizedBox.shrink();
    }
  }

  Widget _buildApprovedSection(Order order, OrderProvider provider) {
    if (order.deliveryStatusEnum == DeliveryStatus.declined) {
      return _buildDeliveryDeclinedInfo(order);
    }

    final isOutForDelivery = order.isOutForDelivery;
    // Credit orders ship on account; cash orders need an accepted payment.
    final canDispatch = order.canBeDispatched;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (order.financeApproved) ...[
          _buildPaymentVerifiedPanel(order),
          const SizedBox(height: 16),
        ],
        if (isOutForDelivery)
          _buildOutForDeliveryActions(order, provider)
        else if (canDispatch)
          _buildDispatchAction(order, provider)
        else
          _buildAwaitingPaymentInfo(order),
      ],
    );
  }

  Widget _buildPaymentVerifiedPanel(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.verified, color: AppColors.success),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Finance Approved - Ready for Dispatch',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: AppColors.success,
                    ),
                  ),
                ),
              ],
            ),
            if (order.financeVerifiedAt != null) ...[
              const SizedBox(height: 8),
              Text(
                'Payment accepted by Finance on ${Formatters.dateTime(order.financeVerifiedAt)}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusBadge.fromDeliveryStatus(order.deliveryStatusEnum),
                if (order.isPaymentReady)
                  const StatusBadge(
                    label: 'Ready for Dispatch',
                    backgroundColor: AppColors.primaryGreen,
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDispatchAction(Order order, OrderProvider provider) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Delivery Dispatch',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              order.isCashOrder
                  ? 'Finance has accepted the payment for this cash order. '
                        'You can now dispatch it.'
                  : 'Credit order — it can be dispatched now. Record how it '
                        'is sent (truck, bus, …).',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            AppButton(
              label: 'Dispatch — Out for Delivery',
              onPressed: () => _confirmAndDispatch(order, provider),
              backgroundColor: AppColors.info,
              icon: Icons.local_shipping_outlined,
            ),
            const SizedBox(height: 8),
            AppButton(
              label: 'Decline Delivery',
              onPressed: () => _showDeclineDeliveryDialog(order, provider),
              foregroundColor: AppColors.error,
              icon: Icons.cancel_outlined,
              isOutlined: true,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAwaitingPaymentInfo(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.hourglass_top, color: AppColors.warning),
                const SizedBox(width: 8),
                Text(
                  'Cash order — awaiting payment',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.warning,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Dispatch is not yet allowed. The franchise partner must submit a '
              'payment for this order, and Finance must verify AND accept '
              '(approve) it before this order can be dispatched.',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
            if (order.expectedDeliveryDate != null) ...[
              const SizedBox(height: 12),
              Text(
                'Expected Delivery: ${Formatters.dateTime(order.expectedDeliveryDate!)}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
            if (order.staffNotes != null && order.staffNotes!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                'Notes: ${order.staffNotes}',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildOutForDeliveryActions(Order order, OrderProvider provider) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.local_shipping, color: AppColors.info),
                const SizedBox(width: 8),
                Text(
                  'Out for Delivery',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.info,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'The order has been dispatched. Confirm when it has been '
              'delivered to the franchise partner.',
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            AppButton(
              label: 'Mark as Delivered',
              onPressed: () => _confirmMarkDelivered(order, provider),
              backgroundColor: AppColors.success,
              icon: Icons.check_circle_outline,
            ),
            const SizedBox(height: 8),
            AppButton(
              label: 'Decline Delivery',
              onPressed: () => _showDeclineDeliveryDialog(order, provider),
              foregroundColor: AppColors.error,
              icon: Icons.cancel_outlined,
              isOutlined: true,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeliveryDeclinedInfo(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.report_problem, color: AppColors.error),
                const SizedBox(width: 8),
                Text(
                  'Delivery Declined',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.error,
                  ),
                ),
              ],
            ),
            if (order.deliveryDeclinedReason != null) ...[
              const SizedBox(height: 12),
              Text(
                'Reason: ${order.deliveryDeclinedReason}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _confirmAndDispatch(Order order, OrderProvider provider) async {
    String means = 'truck';
    final vehicleController = TextEditingController();
    final remarksController = TextEditingController();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Dispatch Order'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('How is order ${order.displayOrderNumber} being sent?'),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: means,
                  decoration: const InputDecoration(labelText: 'Dispatched by'),
                  items: Order.dispatchMeansLabels.entries
                      .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                      .toList(),
                  onChanged: (v) => setDialogState(() => means = v ?? 'truck'),
                ),
                const SizedBox(height: 12),
                AppTextField(
                  controller: vehicleController,
                  label: 'Vehicle / bus company / reg. no.',
                ),
                const SizedBox(height: 12),
                AppTextField(
                  controller: remarksController,
                  label: 'Remarks',
                  maxLines: 2,
                ),
              ],
            ),
          ),
          actions: [
            AppButton(
              label: 'Cancel',
              onPressed: () => Navigator.pop(context, false),
              isOutlined: true,
            ),
            AppButton(
              label: 'Dispatch',
              onPressed: () => Navigator.pop(context, true),
              backgroundColor: AppColors.info,
            ),
          ],
        ),
      ),
    );
    final vehicle = vehicleController.text.trim();
    final remarks = remarksController.text.trim();
    vehicleController.dispose();
    remarksController.dispose();
    if (confirmed != true || !mounted) return;

    final success = await provider.dispatchOrder(
      order.id,
      means: means,
      vehicle: vehicle,
      remarks: remarks,
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          success ? 'Order dispatched' : (provider.error ?? 'Failed to dispatch order'),
        ),
        backgroundColor: success ? AppColors.success : AppColors.error,
      ),
    );
  }

  Future<void> _confirmMarkDelivered(
    Order order,
    OrderProvider provider,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Mark as Delivered'),
        content: Text(
          'Confirm order ${order.displayOrderNumber} has been delivered?',
        ),
        actions: [
          AppButton(
            label: 'Cancel',
            onPressed: () => Navigator.pop(context, false),
            isOutlined: true,
          ),
          AppButton(
            label: 'Mark Delivered',
            onPressed: () => Navigator.pop(context, true),
            backgroundColor: AppColors.success,
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final success = await provider.markDelivered(order.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          success ? 'Order marked as delivered' : 'Failed to mark as delivered',
        ),
        backgroundColor: success ? AppColors.success : AppColors.error,
      ),
    );
  }

  void _showDeclineDeliveryDialog(Order order, OrderProvider provider) {
    final reasonController = TextEditingController();
    String? errorText;

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Decline Delivery'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AppTextField(
                label: 'Reason for declining delivery',
                hint: 'Minimum 5 characters',
                maxLines: 3,
                controller: reasonController,
                onChanged: (value) {
                  if (value.trim().isNotEmpty && errorText != null) {
                    setDialogState(() => errorText = null);
                  }
                },
              ),
              if (errorText != null) ...[
                const SizedBox(height: 6),
                Text(
                  errorText!,
                  style: const TextStyle(
                    color: AppColors.error,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ],
          ),
          actions: [
            AppButton(
              label: 'Cancel',
              onPressed: () => Navigator.pop(context),
              isOutlined: true,
            ),
            AppButton(
              label: 'Decline',
              onPressed: () async {
                final reason = reasonController.text.trim();
                if (reason.length < 5) {
                  setDialogState(
                    () => errorText = 'Reason must be at least 5 characters',
                  );
                  return;
                }
                final success = await provider.declineDelivery(
                  order.id,
                  reason,
                );
                if (!context.mounted) return;
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      success
                          ? 'Delivery declined'
                          : 'Failed to decline delivery',
                    ),
                    backgroundColor: success
                        ? AppColors.success
                        : AppColors.error,
                  ),
                );
              },
              backgroundColor: AppColors.error,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPendingActions(Order order, OrderProvider provider) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Actions',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            AppButton(
              label: 'Approve',
              onPressed: () => _showApproveDialog(order, provider),
              backgroundColor: AppColors.success,
              icon: Icons.check_circle_outline,
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: AppButton(
                    label: 'Decline',
                    onPressed: () => _showDeclineDialog(order, provider),
                    foregroundColor: AppColors.error,
                    icon: Icons.cancel_outlined,
                    isOutlined: true,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: AppButton(
                    label: 'Adjust',
                    onPressed: () => _showAdjustDialog(order, provider),
                    foregroundColor: AppColors.info,
                    icon: Icons.edit_outlined,
                    isOutlined: true,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeclinedInfo(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.cancel, color: AppColors.error),
                const SizedBox(width: 8),
                Text(
                  'Order Declined',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.error,
                  ),
                ),
              ],
            ),
            if (order.declineReason != null &&
                order.declineReason!.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                'Reason: ${order.declineReason}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildDeliveredInfo(Order order) {
    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.local_shipping, color: AppColors.primaryGreen),
                const SizedBox(width: 8),
                Text(
                  'Order Delivered',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: AppColors.primaryGreen,
                  ),
                ),
              ],
            ),
            if (order.deliveredAt != null) ...[
              const SizedBox(height: 12),
              Text(
                'Delivered on ${Formatters.dateTime(order.deliveredAt!)}',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildFinancePaymentDetails(Order order) {
    final payments = context.watch<PaymentProvider>().payments;
    final matches = payments.where(
      (payment) =>
          payment.orders.any((linkedOrder) => linkedOrder.id == order.id),
    );
    final payment = matches.isEmpty ? null : matches.first;

    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Finance Payment Details',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            _buildDetailRow(
              'Status',
              payment?.statusEnum.displayName ?? order.paymentStatusLabel,
            ),
            if (payment != null) ...[
              _buildDetailRow('Amount', Formatters.currency(payment.amount)),
              _buildDetailRow(
                'Reference',
                payment.transactionReference.isEmpty
                    ? 'Not provided'
                    : payment.transactionReference,
              ),
              if (payment.bankName != null && payment.bankName!.isNotEmpty)
                _buildDetailRow('Bank', payment.bankName!),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 88,
            child: Text(
              label,
              style: const TextStyle(color: AppColors.textSecondary),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  void _showApproveDialog(Order order, OrderProvider provider) {
    final deliveryController = TextEditingController();
    final notesController = TextEditingController();
    DateTime? selectedDate = DateTime.now().add(const Duration(days: 3));
    deliveryController.text = Formatters.date(selectedDate);
    String notes = '';
    String? dateError;

    Future<void> pickDate(StateSetter setDialogState) async {
      final date = await showDatePicker(
        context: context,
        initialDate:
            selectedDate ?? DateTime.now().add(const Duration(days: 3)),
        firstDate: DateTime.now(),
        lastDate: DateTime.now().add(const Duration(days: 90)),
      );
      if (date != null) {
        setDialogState(() {
          selectedDate = date;
          dateError = null;
          deliveryController.text = Formatters.date(date);
        });
      }
    }

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Approve Order'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              InkWell(
                borderRadius: BorderRadius.circular(10),
                onTap: () => pickDate(setDialogState),
                child: AppTextField(
                  label: 'Expected Delivery Date',
                  readOnly: true,
                  controller: deliveryController,
                  hint: 'Select the delivery date',
                  suffixIcon: IconButton(
                    icon: const Icon(
                      Icons.calendar_month,
                      color: AppColors.primaryGreen,
                    ),
                    onPressed: () => pickDate(setDialogState),
                  ),
                ),
              ),
              if (dateError != null) ...[
                const SizedBox(height: 6),
                Text(
                  dateError!,
                  style: const TextStyle(
                    color: AppColors.error,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              AppTextField(
                label: 'Notes (optional)',
                maxLines: 3,
                controller: notesController,
                onChanged: (value) => notes = value,
              ),
            ],
          ),
          actions: [
            AppButton(
              label: 'Cancel',
              onPressed: () => Navigator.pop(context),
              isOutlined: true,
            ),
            AppButton(
              label: 'Approve',
              onPressed: () async {
                if (selectedDate == null) {
                  setDialogState(
                    () => dateError = 'Please select an expected delivery date',
                  );
                  return;
                }
                final success = await provider.approveOrder(
                  order.id,
                  deliveryDate: selectedDate!.toIso8601String(),
                  notes: notes.isNotEmpty ? notes : null,
                );
                if (!context.mounted) return;
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      success ? 'Order approved' : 'Failed to approve order',
                    ),
                    backgroundColor: success
                        ? AppColors.success
                        : AppColors.error,
                  ),
                );
              },
              backgroundColor: AppColors.success,
            ),
          ],
        ),
      ),
    );
  }

  void _showDeclineDialog(Order order, OrderProvider provider) {
    final reasonController = TextEditingController();
    String? errorText;

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Decline Order'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AppTextField(
                label: 'Reason for declining',
                hint: 'Specify why this order is declined',
                maxLines: 3,
                controller: reasonController,
                onChanged: (value) {
                  if (value.trim().isNotEmpty && errorText != null) {
                    setDialogState(() => errorText = null);
                  }
                },
              ),
              if (errorText != null) ...[
                const SizedBox(height: 6),
                Text(
                  errorText!,
                  style: const TextStyle(
                    color: AppColors.error,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ],
          ),
          actions: [
            AppButton(
              label: 'Cancel',
              onPressed: () => Navigator.pop(context),
              isOutlined: true,
            ),
            AppButton(
              label: 'Decline',
              onPressed: () async {
                final reason = reasonController.text.trim();
                if (reason.isEmpty) {
                  setDialogState(
                    () => errorText = 'A decline reason is required',
                  );
                  return;
                }
                final success = await provider.declineOrder(order.id, reason);
                if (!context.mounted) return;
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      success ? 'Order declined' : 'Failed to decline order',
                    ),
                    backgroundColor: success
                        ? AppColors.success
                        : AppColors.error,
                  ),
                );
              },
              backgroundColor: AppColors.error,
            ),
          ],
        ),
      ),
    );
  }

  void _showAdjustDialog(Order order, OrderProvider provider) {
    _adjustedQuantities = {
      for (var item in order.items) item.productId: item.quantity.toDouble(),
    };

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Adjust Quantities'),
          content: SizedBox(
            width: double.maxFinite,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: order.items.map((item) {
                  final controller = TextEditingController(
                    text: item.quantity.toString(),
                  );
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Text(
                            item.productName,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                        SizedBox(
                          width: 100,
                          child: AppTextField(
                            label: 'Qty',
                            controller: controller,
                            keyboardType: TextInputType.number,
                            onChanged: (value) {
                              final qty = double.tryParse(value) ?? 0;
                              setDialogState(() {
                                _adjustedQuantities[item.productId] = qty;
                              });
                            },
                          ),
                        ),
                      ],
                    ),
                  );
                }).toList(),
              ),
            ),
          ),
          actions: [
            AppButton(
              label: 'Cancel',
              onPressed: () => Navigator.pop(context),
              isOutlined: true,
            ),
            AppButton(
              label: 'Save Adjustments',
              onPressed: () async {
                final itemsPayload = order.items
                    .map(
                      (item) => {
                        'order_item_id': int.tryParse(item.id) ?? item.id,
                        'adjusted_quantity':
                            _adjustedQuantities[item.productId]?.toInt() ??
                            item.quantity,
                      },
                    )
                    .toList();
                final success = await provider.adjustOrder(order.id, {
                  'items': itemsPayload,
                });
                if (!context.mounted) return;
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      success ? 'Order adjusted' : 'Failed to adjust order',
                    ),
                    backgroundColor: success
                        ? AppColors.success
                        : AppColors.error,
                  ),
                );
              },
              backgroundColor: AppColors.info,
            ),
          ],
        ),
      ),
    );
  }
}

class _TimelineStep {
  final String label;
  final IconData icon;
  final bool isActive;

  _TimelineStep(this.label, this.icon, this.isActive);
}
