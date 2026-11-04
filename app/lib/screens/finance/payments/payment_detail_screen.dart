import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/enums/app_enums.dart';
import '../../../core/utils/formatters.dart';
import '../../../models/payment.dart';
import '../../../providers/payment_provider.dart';
import '../../../services/api/api_service.dart';
import '../../../widgets/common/status_badge.dart';
import '../../../widgets/common/loading_view.dart';

class FinancePaymentDetailScreen extends StatefulWidget {
  final String paymentId;

  const FinancePaymentDetailScreen({super.key, required this.paymentId});

  @override
  State<FinancePaymentDetailScreen> createState() =>
      _FinancePaymentDetailScreenState();
}

class _FinancePaymentDetailScreenState
    extends State<FinancePaymentDetailScreen> {
  Payment? _payment;
  bool _isLoading = true;
  bool _isProcessing = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadPayment();
  }

  Future<void> _loadPayment() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final api = context.read<PaymentProvider>();
      final payments = api.payments;
      final pending = api.pendingFinancePayments;

      // Try to find in loaded payments first
      Payment? found;
      for (final p in [...payments, ...pending]) {
        if (p.id.toString() == widget.paymentId.toString()) {
          found = p;
          break;
        }
      }

      // If not found, fetch from API
      if (found == null) {
        final apiService = context.read<ApiService>();
        found = await apiService.getFinancePayment(widget.paymentId);
      }

      if (mounted) {
        setState(() {
          _payment = found;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'Failed to load payment details';
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Payment Details')),
      body: _isLoading
          ? const Center(child: LoadingView())
          : _error != null
          ? _buildError()
          : _buildContent(),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline, size: 48, color: AppColors.error),
          const SizedBox(height: 16),
          Text(_error!, style: const TextStyle(color: AppColors.textSecondary)),
          const SizedBox(height: 16),
          ElevatedButton(onPressed: _loadPayment, child: const Text('Retry')),
        ],
      ),
    );
  }

  Widget _buildContent() {
    final payment = _payment!;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildStatusHeader(payment),
          const SizedBox(height: 24),
          _buildDetailSection('Payment Information', [
            _buildDetailRow('Payment Number', payment.paymentNumber ?? 'N/A'),
            _buildDetailRow('Amount', Formatters.currency(payment.amount)),
            _buildDetailRow(
              'Payment Method',
              _formatPaymentMethod(payment.paymentMethod),
            ),
            _buildDetailRow(
              'Transaction Reference',
              payment.transactionReference.isNotEmpty
                  ? payment.transactionReference
                  : 'N/A',
            ),
            if (payment.bankName != null && payment.bankName!.isNotEmpty)
              _buildDetailRow('Bank', payment.bankName!),
          ]),
          const SizedBox(height: 16),
          _buildDetailSection('Franchise Information', [
            _buildDetailRow(
              'Franchise',
              payment.franchiseName.isNotEmpty
                  ? payment.franchiseName
                  : 'Franchise #${payment.franchiseId}',
            ),
          ]),
          const SizedBox(height: 16),
          _buildDetailSection('Timeline', [
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
            _buildDetailRow(
              'Last Updated',
              Formatters.dateTime(payment.updatedAt),
            ),
          ]),
          if (payment.rejectionReason != null &&
              payment.rejectionReason!.isNotEmpty) ...[
            const SizedBox(height: 16),
            _buildDetailSection('Rejection Reason', [
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                child: Text(
                  payment.rejectionReason!,
                  style: const TextStyle(color: AppColors.error, fontSize: 14),
                ),
              ),
            ]),
          ],
          const SizedBox(height: 24),
          if (payment.statusEnum == PaymentStatus.pending)
            _buildPendingActions(payment),
          if (payment.statusEnum == PaymentStatus.verified)
            _buildVerifiedActions(payment),
        ],
      ),
    );
  }

  Widget _buildStatusHeader(Payment payment) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _statusColor(payment.status).withAlpha(26),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _statusColor(payment.status).withAlpha(51)),
      ),
      child: Column(
        children: [
          Icon(
            _statusIcon(payment.status),
            size: 40,
            color: _statusColor(payment.status),
          ),
          const SizedBox(height: 12),
          Text(
            Formatters.currency(payment.amount),
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 8),
          StatusBadge.fromPaymentStatus(payment.status),
          const SizedBox(height: 4),
          Text(
            payment.paymentNumber ?? '',
            style: const TextStyle(
              fontSize: 13,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailSection(String title, List<Widget> children) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.surfaceWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.divider),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
          ),
          ...children,
        ],
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
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
          Flexible(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w500,
                color: AppColors.textPrimary,
              ),
              textAlign: TextAlign.end,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPendingActions(Payment payment) {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: _isProcessing ? null : () => _showRejectDialog(payment),
            icon: const Icon(Icons.cancel_outlined, color: AppColors.error),
            label: const Text(
              'Reject',
              style: TextStyle(color: AppColors.error),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AppColors.error),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: ElevatedButton.icon(
            onPressed: _isProcessing ? null : () => _showVerifyDialog(payment),
            icon: const Icon(Icons.verified_outlined, color: Colors.white),
            label: const Text('Verify', style: TextStyle(color: Colors.white)),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.info,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildVerifiedActions(Payment payment) {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: _isProcessing ? null : () => _showRejectDialog(payment),
            icon: const Icon(Icons.cancel_outlined, color: AppColors.error),
            label: const Text(
              'Reject',
              style: TextStyle(color: AppColors.error),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AppColors.error),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: ElevatedButton.icon(
            onPressed: _isProcessing ? null : () => _acceptPayment(payment),
            icon: const Icon(Icons.check_circle_outline, color: Colors.white),
            label: const Text(
              'Accept Payment',
              style: TextStyle(color: Colors.white),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.success,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
        ),
      ],
    );
  }

  void _showVerifyDialog(Payment payment) {
    final amountController = TextEditingController(
      text: payment.amount.toStringAsFixed(0),
    );
    final notesController = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Verify Payment'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Payment: ${payment.paymentNumber}',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: amountController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Verified Amount',
                prefixText: 'UGX ',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: notesController,
              decoration: const InputDecoration(
                labelText: 'Notes (optional)',
                border: OutlineInputBorder(),
              ),
              maxLines: 2,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.of(ctx).pop();
              final amount =
                  double.tryParse(amountController.text) ?? payment.amount;
              await _verifyPayment(
                payment,
                verifiedAmount: amount,
                notes: notesController.text.isNotEmpty
                    ? notesController.text
                    : null,
              );
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.info),
            child: const Text('Verify', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _showRejectDialog(Payment payment) {
    final reasonController = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reject Payment'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Payment: ${payment.paymentNumber}',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(
                labelText: 'Rejection Reason *',
                border: OutlineInputBorder(),
              ),
              maxLines: 3,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              if (reasonController.text.trim().isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Please provide a rejection reason'),
                    backgroundColor: AppColors.error,
                  ),
                );
                return;
              }
              Navigator.of(ctx).pop();
              await _rejectPayment(payment, reasonController.text.trim());
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error),
            child: const Text('Reject', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Future<void> _verifyPayment(
    Payment payment, {
    double? verifiedAmount,
    String? notes,
  }) async {
    setState(() => _isProcessing = true);
    final success = await context.read<PaymentProvider>().verifyPaymentById(
      payment.id.toString(),
      verifiedAmount: verifiedAmount,
      notes: notes,
    );
    if (mounted) {
      setState(() => _isProcessing = false);
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment verified successfully'),
            backgroundColor: AppColors.success,
          ),
        );
        await _loadPayment();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Failed to verify payment'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> _acceptPayment(Payment payment) async {
    setState(() => _isProcessing = true);
    final success = await context.read<PaymentProvider>().acceptPaymentById(
      payment.id.toString(),
    );
    if (mounted) {
      setState(() => _isProcessing = false);
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment accepted successfully'),
            backgroundColor: AppColors.success,
          ),
        );
        await _loadPayment();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Failed to accept payment'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> _rejectPayment(Payment payment, String reason) async {
    setState(() => _isProcessing = true);
    final success = await context.read<PaymentProvider>().rejectPaymentById(
      payment.id.toString(),
      reason,
    );
    if (mounted) {
      setState(() => _isProcessing = false);
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment rejected'),
            backgroundColor: AppColors.warning,
          ),
        );
        await _loadPayment();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Failed to reject payment'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  String _formatPaymentMethod(String? method) {
    switch (method) {
      case 'bank_transfer':
        return 'Bank Transfer';
      case 'mobile_money':
        return 'Mobile Money';
      case 'cash':
        return 'Cash';
      default:
        return method ?? 'N/A';
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'pending':
        return AppColors.warning;
      case 'verified':
        return AppColors.info;
      case 'accepted':
        return AppColors.success;
      case 'rejected':
        return AppColors.error;
      default:
        return AppColors.textLight;
    }
  }

  IconData _statusIcon(String status) {
    switch (status) {
      case 'pending':
        return Icons.pending_actions;
      case 'verified':
        return Icons.verified_outlined;
      case 'accepted':
        return Icons.check_circle_outline;
      case 'rejected':
        return Icons.cancel_outlined;
      default:
        return Icons.help_outline;
    }
  }
}
