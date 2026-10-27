import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/enums/app_enums.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/validators.dart';
import '../../../models/order.dart';
import '../../../models/payment.dart';
import '../../../providers/order_provider.dart';
import '../../../providers/payment_provider.dart';
import '../../../widgets/common/app_button.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/app_text_field.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';
import '../../../widgets/common/status_badge.dart';

class PaymentScreen extends StatefulWidget {
  final List<String>? preSelectedOrderIds;

  const PaymentScreen({super.key, this.preSelectedOrderIds});

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _referenceController = TextEditingController();
  String? _selectedBank;
  String? _selectedMethod;
  XFile? _proofFile;
  bool _isSubmitting = false;
  final Set<String> _selectedOrderIds = {};

  static const _banks = [
    'Stanbic Bank',
    'Centenary Bank',
    'Bank of Africa',
    'Absa Bank',
    'Equity Bank',
    'DFCU Bank',
    'Standard Chartered',
    'Other',
  ];

  // Cash payments are made by mobile money or bank transfer (client rule).
  static const _methods = ['mobile_money', 'bank_transfer'];
  static const _methodLabels = {
    'bank_transfer': 'Bank Transfer',
    'mobile_money': 'Mobile Money',
  };

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    final provider = context.read<PaymentProvider>();
    provider.loadPayments(silent: provider.payments.isNotEmpty);
    provider.loadAccountSummary(silent: provider.accountSummary != null);
    context.read<OrderProvider>().loadOrders(
      status: 'approved',
      silent: context.read<OrderProvider>().orders.isNotEmpty,
    );
    if (widget.preSelectedOrderIds != null) {
      _selectedOrderIds.addAll(widget.preSelectedOrderIds!);
    }
  }

  @override
  void dispose() {
    _tabController.dispose();
    _amountController.dispose();
    _referenceController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _buildAccountSummary(),
        TabBar(
          controller: _tabController,
          labelColor: AppColors.primaryGreen,
          unselectedLabelColor: AppColors.textSecondary,
          indicatorColor: AppColors.primaryGreen,
          tabs: const [
            Tab(text: 'Submit Payment'),
            Tab(text: 'Payment History'),
          ],
        ),
        Expanded(
          child: TabBarView(
            controller: _tabController,
            children: [_buildSubmitPaymentTab(), _buildPaymentHistoryTab()],
          ),
        ),
      ],
    );
  }

  Widget _buildAccountSummary() {
    return Consumer<PaymentProvider>(
      builder: (context, provider, _) {
        final summary = provider.accountSummary;
        if (summary == null) return const SizedBox.shrink();

        return Container(
          margin: const EdgeInsets.fromLTRB(
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            0,
          ),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [AppColors.primaryGreen, AppColors.primaryGreenDark],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(
              AppConstants.defaultBorderRadius,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Account Summary',
                style: TextStyle(color: Colors.white70, fontSize: 12),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Outstanding',
                          style: TextStyle(color: Colors.white60, fontSize: 11),
                        ),
                        Text(
                          Formatters.currency(summary.outstandingBalance),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Credit Limit',
                          style: TextStyle(color: Colors.white60, fontSize: 11),
                        ),
                        Text(
                          Formatters.currency(summary.creditLimit),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSubmitPaymentTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(AppConstants.defaultPadding),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildApprovedOrdersSection(),
            const SizedBox(height: 16),
            const Text(
              'Payment Details',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _amountController,
              label: 'Amount (UGX)',
              hint: 'Enter payment amount',
              prefixIcon: Icons.attach_money,
              keyboardType: TextInputType.number,
              validator: (v) => Validators.positiveNumber(v, 'Amount'),
            ),
            if (_selectedOrderIds.isNotEmpty) ...[
              const SizedBox(height: 8),
              _buildOrderTotalsBreakdown(),
            ],
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _selectedBank,
              decoration: const InputDecoration(
                labelText: 'Bank Name',
                prefixIcon: Icon(
                  Icons.account_balance,
                  color: AppColors.textLight,
                ),
              ),
              items: _banks
                  .map((b) => DropdownMenuItem(value: b, child: Text(b)))
                  .toList(),
              onChanged: (v) => setState(() => _selectedBank = v),
              validator: (v) => _selectedMethod == 'bank_transfer'
                  ? Validators.required(v, 'Bank name')
                  : null,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _selectedMethod,
              decoration: const InputDecoration(
                labelText: 'Payment Method',
                prefixIcon: Icon(Icons.payment, color: AppColors.textLight),
              ),
              items: _methods
                  .map(
                    (m) => DropdownMenuItem(
                      value: m,
                      child: Text(_methodLabels[m] ?? m),
                    ),
                  )
                  .toList(),
              onChanged: (v) => setState(() => _selectedMethod = v),
              validator: (v) => Validators.required(v, 'Payment method'),
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _referenceController,
              label: 'Transaction Reference',
              hint: 'Enter transaction reference number',
              prefixIcon: Icons.receipt_outlined,
              validator: (v) => Validators.required(v, 'Transaction reference'),
            ),
            const SizedBox(height: 16),
            _buildUploadProof(),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: AppButton(
                label: 'Submit Payment',
                isLoading: _isSubmitting,
                onPressed: _submitPayment,
                icon: Icons.send,
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Order> get _approvedOrders {
    return context
        .read<OrderProvider>()
        .orders
        .where((o) => o.statusEnum == OrderStatus.approved)
        .toList();
  }

  List<Order> get _selectedOrders => _approvedOrders
      .where((o) => _selectedOrderIds.contains(o.id))
      .toList();

  double get _selectedOrdersTotal =>
      _selectedOrders.fold(0.0, (sum, o) => sum + o.displayedAmount);

  double get _selectedOrdersTax =>
      _selectedOrders.fold(0.0, (sum, o) => sum + o.taxAmount);

  Widget _buildApprovedOrdersSection() {
    final orders = _approvedOrders;
    if (orders.isEmpty) {
      return AppCard(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              const Icon(Icons.info_outline, color: AppColors.info, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'You have no approved orders awaiting payment. '
                  'You can still submit a custom payment below.',
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return AppCard(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Pay for Approved Orders',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Select the orders this payment covers. The amount is filled '
              'in automatically and can still be edited.',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            ...orders.map(_buildOrderSelectionChip),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Selected total',
                  style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                ),
                Text(
                  Formatters.currency(_selectedOrdersTotal),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
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

  Widget _buildOrderSelectionChip(Order order) {
    final selected = _selectedOrderIds.contains(order.id);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: () => _onOrderSelected(order),
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: selected
                ? AppColors.primaryGreen.withAlpha(18)
                : AppColors.surfaceWhite,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: selected
                  ? AppColors.primaryGreen
                  : AppColors.divider,
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Row(
            children: [
              Icon(
                selected
                    ? Icons.check_circle
                    : Icons.radio_button_unchecked,
                color: selected
                    ? AppColors.primaryGreen
                    : AppColors.textLight,
                size: 20,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.displayOrderNumber,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${order.items.length} item(s) · ${Formatters.date(order.createdAt)}',
                      style: const TextStyle(
                        fontSize: 11,
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
                    Formatters.currency(order.displayedAmount),
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (order.taxAmount > 0)
                    Text(
                      'Tax ${Formatters.currency(order.taxAmount)}',
                      style: const TextStyle(
                        fontSize: 10,
                        color: AppColors.textLight,
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _onOrderSelected(Order order) {
    setState(() {
      if (_selectedOrderIds.contains(order.id)) {
        _selectedOrderIds.remove(order.id);
      } else {
        _selectedOrderIds.add(order.id);
      }
    });
    if (_selectedOrders.isNotEmpty) {
      _amountController.text =
          _selectedOrdersTotal.toStringAsFixed(0);
    }
  }

  Widget _buildOrderTotalsBreakdown() {
    final subtotal = _selectedOrdersTotal - _selectedOrdersTax;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.primaryGreen.withAlpha(10),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        children: [
          _breakdownRow('Subtotal (excl. tax)', subtotal),
          const SizedBox(height: 4),
          _breakdownRow('Tax (${_selectedOrders.length} order(s))', _selectedOrdersTax),
          const Divider(height: 16),
          _breakdownRow(
            'Outstanding total',
            _selectedOrdersTotal,
            emphasize: true,
          ),
        ],
      ),
    );
  }

  Widget _breakdownRow(String label, double value, {bool emphasize = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: emphasize ? 13 : 12,
            fontWeight: emphasize ? FontWeight.w700 : FontWeight.normal,
            color: AppColors.textSecondary,
          ),
        ),
        Text(
          Formatters.currency(value),
          style: TextStyle(
            fontSize: emphasize ? 14 : 12,
            fontWeight: emphasize ? FontWeight.w700 : FontWeight.w600,
            color: emphasize ? AppColors.primaryGreen : AppColors.textPrimary,
          ),
        ),
      ],
    );
  }

  Widget _buildUploadProof() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Upload Proof of Payment (Optional)',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        if (_proofFile != null)
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.primaryGreen.withAlpha(13),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppColors.primaryGreen.withAlpha(77)),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.image,
                  color: AppColors.primaryGreen,
                  size: 20,
                ),
                const SizedBox(width: 8),
                const Expanded(
                  child: Text(
                    'Proof uploaded',
                    style: TextStyle(
                      color: AppColors.primaryGreen,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close, size: 16),
                  onPressed: () => setState(() => _proofFile = null),
                ),
              ],
            ),
          )
        else
          OutlinedButton.icon(
            onPressed: _pickProof,
            icon: const Icon(Icons.camera_alt_outlined, size: 18),
            label: const Text('Upload Proof'),
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 12),
              minimumSize: const Size(double.infinity, 0),
            ),
          ),
      ],
    );
  }

  Widget _buildPaymentHistoryTab() {
    return Consumer<PaymentProvider>(
      builder: (context, provider, _) {
        if (provider.isLoading) {
          return const LoadingView(message: 'Loading payments...');
        }

        if (provider.error != null) {
          return ErrorView(
            message: provider.error!,
            onRetry: () => provider.loadPayments(),
          );
        }

        if (provider.payments.isEmpty) {
          return const EmptyView(
            message: 'No payment history yet',
            title: 'No Payments',
            icon: Icons.receipt_long_outlined,
          );
        }

        return RefreshIndicator(
          onRefresh: () => provider.loadPayments(),
          child: ListView.builder(
            padding: const EdgeInsets.all(AppConstants.defaultPadding),
            itemCount: provider.payments.length,
            itemBuilder: (context, index) =>
                _buildPaymentCard(provider.payments[index]),
          ),
        );
      },
    );
  }

  Widget _buildPaymentCard(Payment payment) {
    return AppCard(
      onTap: () => _showPaymentDetail(payment),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppColors.primaryGreen.withAlpha(26),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(
              Icons.receipt_long_outlined,
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
                  (payment.paymentNumber?.isNotEmpty ?? false)
                      ? payment.paymentNumber!
                      : 'PAY-${payment.id}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${payment.paymentMethod ?? "N/A"} · ${Formatters.date(payment.submittedAt)}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                if (payment.transactionReference.isNotEmpty)
                  Text(
                    'Ref: ${payment.transactionReference}',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppColors.textLight,
                    ),
                  ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                Formatters.currency(payment.amount),
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 4),
              StatusBadge.fromPaymentStatus(payment.status),
            ],
          ),
        ],
      ),
    );
  }

  void _showPaymentDetail(Payment payment) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) => _PaymentDetailSheet(payment: payment),
    );
  }

  Future<void> _pickProof() async {
    final picker = ImagePicker();
    final image = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1200,
      maxHeight: 1200,
      imageQuality: 85,
    );
    if (image != null) {
      setState(() => _proofFile = image);
    }
  }

  Future<void> _submitPayment() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSubmitting = true);

    try {
      final orderIds = _selectedOrders.map((o) => o.id).toList();
      final success = await context.read<PaymentProvider>().submitPayment({
        'amount': double.parse(_amountController.text),
        'bank_name': _selectedBank,
        'payment_method': _selectedMethod ?? 'bank_transfer',
        'transaction_reference': _referenceController.text.trim(),
        if (orderIds.isNotEmpty) 'order_ids': orderIds,
        if (_proofFile != null) 'proof_file_path': _proofFile!.path,
      });

      if (!mounted) return;

      if (success) {
        _amountController.clear();
        _referenceController.clear();
        setState(() {
          _selectedBank = null;
          _selectedMethod = null;
          _proofFile = null;
          _selectedOrderIds.clear();
        });

        _tabController.animateTo(1);

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Payment submitted successfully'),
            backgroundColor: AppColors.primaryGreen,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Failed to submit payment'),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to submit payment: $e'),
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

class _PaymentDetailSheet extends StatelessWidget {
  final Payment payment;

  const _PaymentDetailSheet({required this.payment});

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.65,
      minChildSize: 0.3,
      maxChildSize: 0.9,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: const EdgeInsets.all(AppConstants.defaultPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
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
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    payment.id,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  StatusBadge.fromPaymentStatus(payment.status),
                ],
              ),
              const SizedBox(height: 16),
              _buildDetailRow('Amount', Formatters.currency(payment.amount)),
              _buildDetailRow('Payment Method', payment.paymentMethod ?? 'N/A'),
              _buildDetailRow('Bank', payment.bankName ?? 'N/A'),
              _buildDetailRow('Reference', payment.transactionReference),
              _buildDetailRow(
                'Submitted',
                Formatters.dateTime(payment.submittedAt),
              ),
              if (payment.verifiedAt != null)
                _buildDetailRow(
                  'Verified',
                  Formatters.dateTime(payment.verifiedAt),
                ),
              if (payment.verifiedBy != null)
                _buildDetailRow('Verified By', payment.verifiedBy!),
              if (payment.orders.isNotEmpty) ...[
                const SizedBox(height: 12),
                const Text(
                  'Linked Orders',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                ...payment.orders.map(
                  (o) => _buildDetailRow(
                    o.orderNumber ?? o.id,
                    Formatters.currency(o.totalAmount),
                  ),
                ),
              ],
              if (payment.rejectionReason != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.error.withAlpha(13),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.info_outline,
                        color: AppColors.error,
                        size: 18,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          payment.rejectionReason!,
                          style: const TextStyle(
                            color: AppColors.error,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              if (payment.statusEnum == PaymentStatus.infoRequested ||
                  payment.infoRequestNote != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.warning.withAlpha(18),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.help_outline,
                        color: AppColors.warning,
                        size: 18,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Finance requested more information',
                              style: TextStyle(
                                color: AppColors.warning,
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            if (payment.infoRequestNote != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                payment.infoRequestNote!,
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                            if (payment.infoRequestedBy != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                'Requested by: ${payment.infoRequestedBy}',
                                style: const TextStyle(
                                  color: AppColors.textLight,
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Expanded(
                child: ListView(
                  controller: scrollController,
                  children: [
                    const Text(
                      'Status Timeline',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _buildStatusTimeline(),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
            ),
          ),
          Text(
            value,
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusTimeline() {
    final isInfoRequested = payment.statusEnum == PaymentStatus.infoRequested;
    final steps = <_TimelineStepData>[
      _TimelineStepData(
        title: 'Payment Submitted',
        subtitle: Formatters.dateTime(payment.submittedAt),
        isCompleted: true,
      ),
      _TimelineStepData(
        title: 'Under Review',
        subtitle: isInfoRequested
            ? 'Additional information requested'
            : payment.statusEnum.index >= PaymentStatus.verified.index
                ? 'Review complete'
                : 'Pending review',
        isCompleted:
            isInfoRequested || payment.statusEnum.index >= PaymentStatus.verified.index,
      ),
    ];

    if (isInfoRequested) {
      steps.add(_TimelineStepData(
        title: 'More Info Needed',
        subtitle: payment.infoRequestNote,
        isCompleted: true,
      ));
      steps.add(_TimelineStepData(
        title: 'Re-submission',
        subtitle: 'Upload updated proof after addressing the request',
        isCompleted: false,
      ));
    } else {
      steps.add(_TimelineStepData(
        title: payment.statusEnum == PaymentStatus.rejected
            ? 'Payment Rejected'
            : 'Payment Accepted',
        subtitle: payment.statusEnum == PaymentStatus.rejected
            ? payment.rejectionReason
            : payment.verifiedAt != null
            ? Formatters.dateTime(payment.verifiedAt)
            : 'Pending',
        isCompleted:
            payment.statusEnum == PaymentStatus.accepted ||
            payment.statusEnum == PaymentStatus.rejected,
      ));
    }

    return Column(
      children: List.generate(steps.length, (index) {
        final step = steps[index];
        final isLast = index == steps.length - 1;
        final color = step.isCompleted
            ? (isInfoRequested && index == 2
                ? AppColors.warning
                : AppColors.primaryGreen)
            : AppColors.textLight;

        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Column(
              children: [
                Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    color: step.isCompleted
                        ? color.withAlpha(26)
                        : AppColors.backgroundLight,
                    shape: BoxShape.circle,
                    border: Border.all(color: color, width: 2),
                  ),
                  child: Icon(
                    step.isCompleted ? Icons.check : Icons.circle,
                    size: 12,
                    color: color,
                  ),
                ),
                if (!isLast)
                  Container(
                    width: 2,
                    height: 24,
                    color: step.isCompleted
                        ? color
                        : AppColors.border,
                  ),
              ],
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      step.title,
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                        color: step.isCompleted
                            ? AppColors.textPrimary
                            : AppColors.textSecondary,
                      ),
                    ),
                    if (step.subtitle != null)
                      Text(
                        step.subtitle!,
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
      }),
    );
  }
}

class _TimelineStepData {
  final String title;
  final String? subtitle;
  final bool isCompleted;

  const _TimelineStepData({
    required this.title,
    this.subtitle,
    required this.isCompleted,
  });
}
