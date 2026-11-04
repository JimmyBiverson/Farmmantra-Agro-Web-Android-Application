import 'package:flutter/material.dart';
import '../../core/enums/app_enums.dart';
import '../../core/theme/app_colors.dart';

class StatusBadge extends StatelessWidget {
  final String label;
  final Color backgroundColor;
  final Color textColor;

  const StatusBadge({
    super.key,
    required this.label,
    required this.backgroundColor,
    this.textColor = Colors.white,
  });

  factory StatusBadge.fromOrderStatus(dynamic status) {
    OrderStatus statusEnum;
    if (status is OrderStatus) {
      statusEnum = status;
    } else {
      statusEnum = OrderStatus.values.firstWhere(
        (s) => s.name == status?.toString(),
        orElse: () => OrderStatus.pending,
      );
    }

    switch (statusEnum) {
      case OrderStatus.pending:
        return StatusBadge(
          label: 'Pending',
          backgroundColor: AppColors.statusPending,
        );
      case OrderStatus.approved:
        return StatusBadge(
          label: 'Approved',
          backgroundColor: AppColors.statusApproved,
        );
      case OrderStatus.declined:
        return StatusBadge(
          label: 'Declined',
          backgroundColor: AppColors.statusDeclined,
        );
      case OrderStatus.adjusted:
        return StatusBadge(
          label: 'Adjusted',
          backgroundColor: AppColors.statusAdjusted,
        );
      case OrderStatus.delivered:
        return StatusBadge(
          label: 'Delivered',
          backgroundColor: AppColors.statusDelivered,
        );
      case OrderStatus.cancelled:
        return StatusBadge(
          label: 'Cancelled',
          backgroundColor: AppColors.textLight,
        );
    }
  }

  factory StatusBadge.fromPaymentStatus(dynamic status) {
    PaymentStatus statusEnum;
    if (status is PaymentStatus) {
      statusEnum = status;
    } else {
      statusEnum = PaymentStatus.values.firstWhere(
        (s) => s.name == status?.toString(),
        orElse: () => PaymentStatus.pending,
      );
    }

    switch (statusEnum) {
      case PaymentStatus.pending:
        return StatusBadge(
          label: 'Pending',
          backgroundColor: AppColors.paymentPending,
        );
      case PaymentStatus.verified:
        return StatusBadge(
          label: 'Verified',
          backgroundColor: AppColors.paymentVerified,
        );
      case PaymentStatus.accepted:
        return StatusBadge(
          label: 'Accepted',
          backgroundColor: AppColors.paymentAccepted,
        );
      case PaymentStatus.rejected:
        return StatusBadge(
          label: 'Rejected',
          backgroundColor: AppColors.paymentRejected,
        );
      case PaymentStatus.infoRequested:
        return StatusBadge(
          label: 'Info Requested',
          backgroundColor: AppColors.warning,
        );
    }
  }

  factory StatusBadge.fromDeliveryStatus(dynamic status) {
    DeliveryStatus statusEnum;
    if (status is DeliveryStatus) {
      statusEnum = status;
    } else {
      statusEnum = DeliveryStatus.values.firstWhere(
        (s) => s.name == status?.toString(),
        orElse: () => DeliveryStatus.pending,
      );
    }

    switch (statusEnum) {
      case DeliveryStatus.pending:
        return StatusBadge(
          label: 'Pending',
          backgroundColor: AppColors.statusPending,
        );
      case DeliveryStatus.paymentVerified:
        return StatusBadge(
          label: 'Payment Verified',
          backgroundColor: AppColors.paymentVerified,
        );
      case DeliveryStatus.readyForDelivery:
        return StatusBadge(
          label: 'Ready for Delivery',
          backgroundColor: AppColors.statusApproved,
        );
      case DeliveryStatus.outForDelivery:
        return StatusBadge(
          label: 'Out for Delivery',
          backgroundColor: AppColors.info,
        );
      case DeliveryStatus.delivered:
        return StatusBadge(
          label: 'Delivered',
          backgroundColor: AppColors.statusDelivered,
        );
      case DeliveryStatus.confirmed:
        return StatusBadge(
          label: 'Confirmed',
          backgroundColor: AppColors.success,
        );
      case DeliveryStatus.declined:
        return StatusBadge(
          label: 'Delivery Declined',
          backgroundColor: AppColors.statusDeclined,
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: textColor,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
