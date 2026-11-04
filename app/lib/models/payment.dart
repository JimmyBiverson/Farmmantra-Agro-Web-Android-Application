import '../core/enums/app_enums.dart';

class Payment {
  final String id;
  final String? paymentNumber;
  final String franchiseId;
  final String franchiseName;
  final double amount;
  final double? verifiedAmount;
  final String transactionReference;
  final String? bankName;
  final String? paymentMethod;
  final String status;
  final String? proofUrl;
  final String? rejectionReason;
  final String? infoRequestNote;
  final String? infoRequestedBy;
  final dynamic infoRequestedAt;
  final String? financeNotes;
  final String? verifiedBy;
  final dynamic verifiedAt;
  final dynamic submittedAt;
  final dynamic updatedAt;
  final List<LinkedOrder> orders;

  const Payment({
    required this.id,
    this.paymentNumber,
    required this.franchiseId,
    required this.franchiseName,
    required this.amount,
    this.verifiedAmount,
    required this.transactionReference,
    this.bankName,
    this.paymentMethod,
    required this.status,
    this.proofUrl,
    this.rejectionReason,
    this.infoRequestNote,
    this.infoRequestedBy,
    this.infoRequestedAt,
    this.financeNotes,
    this.verifiedBy,
    this.verifiedAt,
    required this.submittedAt,
    required this.updatedAt,
    this.orders = const [],
  });

  PaymentStatus get statusEnum {
    switch (status) {
      case 'pending':
        return PaymentStatus.pending;
      case 'verified':
        return PaymentStatus.verified;
      case 'accepted':
        return PaymentStatus.accepted;
      case 'rejected':
        return PaymentStatus.rejected;
      case 'info_requested':
        return PaymentStatus.infoRequested;
      default:
        return PaymentStatus.pending;
    }
  }

  factory Payment.fromJson(Map<String, dynamic> json) {
    final franchise = json['franchise'];
    return Payment(
      id: json['id']?.toString() ?? '',
      paymentNumber: json['payment_number'],
      franchiseId: franchise is Map
          ? (franchise['id']?.toString() ??
                json['franchise_id']?.toString() ??
                '')
          : (json['franchise_id']?.toString() ?? ''),
      franchiseName: franchise is Map
          ? (franchise['name'] ?? '')
          : (json['franchise_name'] ?? ''),
      amount: _toDouble(json['amount']),
      verifiedAmount: json['verified_amount'] == null
          ? null
          : _toDouble(json['verified_amount']),
      transactionReference: json['transaction_reference'] ?? '',
      bankName: json['bank_name'],
      paymentMethod: json['payment_method'],
      status: json['status'] ?? 'pending',
      proofUrl: json['proof_of_payment_path'] ?? json['proof_url'],
      rejectionReason: json['rejection_reason'],
      infoRequestNote: json['info_request_note'],
      infoRequestedBy: json['info_requested_by']?.toString(),
      infoRequestedAt: json['info_requested_at'],
      financeNotes: json['finance_notes'],
      verifiedBy: json['verified_by']?.toString(),
      verifiedAt: json['verified_at'],
      submittedAt: json['submitted_at'] ?? json['created_at'] ?? '',
      updatedAt: json['updated_at'] ?? '',
      orders:
          (json['orders'] as List<dynamic>?)
              ?.map((o) => LinkedOrder.fromJson(o))
              .toList() ??
          [],
    );
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0;
    return 0;
  }
}

class LinkedOrder {
  final String id;
  final String? orderNumber;
  final double totalAmount;
  final double taxAmount;
  final String status;
  final String? deliveryStatus;
  final double? allocatedAmount;

  const LinkedOrder({
    required this.id,
    this.orderNumber,
    this.totalAmount = 0,
    this.taxAmount = 0,
    this.status = 'pending',
    this.deliveryStatus,
    this.allocatedAmount,
  });

  factory LinkedOrder.fromJson(Map<String, dynamic> json) {
    return LinkedOrder(
      id: json['id']?.toString() ?? '',
      orderNumber: json['order_number'],
      totalAmount: Payment._toDouble(json['total_amount']),
      taxAmount: Payment._toDouble(json['tax_amount']),
      status: json['status'] ?? 'pending',
      deliveryStatus: json['delivery_status'],
      allocatedAmount: json['pivot'] is Map
          ? Payment._toDouble(json['pivot']['allocated_amount'])
          : null,
    );
  }

  bool get isApproved => status == 'approved';
}

class AccountSummary {
  final double totalSales;
  final double totalPayments;
  final double outstandingBalance;
  final double creditLimit;

  const AccountSummary({
    required this.totalSales,
    required this.totalPayments,
    required this.outstandingBalance,
    required this.creditLimit,
  });

  factory AccountSummary.fromJson(Map<String, dynamic> json) {
    return AccountSummary(
      totalSales: Payment._toDouble(json['total_sales']),
      totalPayments: Payment._toDouble(json['total_payments']),
      outstandingBalance: Payment._toDouble(json['outstanding_balance']),
      creditLimit: Payment._toDouble(json['credit_limit']),
    );
  }

  double get creditUtilization =>
      creditLimit > 0 ? (outstandingBalance / creditLimit) * 100 : 0;
}
