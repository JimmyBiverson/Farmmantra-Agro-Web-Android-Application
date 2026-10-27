import '../core/enums/app_enums.dart';

class Order {
  final String id;
  final String? orderNumber;
  final String franchiseId;
  final String franchiseName;
  final List<OrderItem> items;
  final double totalAmount;
  final double? adjustedAmount;
  final double taxAmount;
  final String status;
  final String deliveryStatus;
  final String? declineReason;
  final String? deliveryDeclinedReason;
  final String? adjustmentNotes;
  final DateTime? expectedDeliveryDate;
  final DateTime? deliveredAt;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final String? staffNotes;
  final String? financeVerifiedBy;
  final DateTime? financeVerifiedAt;
  final String? approvedBy;
  final DateTime? approvedAt;
  final int paymentVerifiedCount;

  /// 'credit' (pay later, within credit limit) or 'cash' (pay before dispatch).
  final String paymentTerms;
  final double amountPaid;
  final String? dispatchMeans;
  final String? dispatchVehicle;
  final String? dispatchRemarks;
  final DateTime? dispatchedAt;
  final String? approvalSignature;
  final String? dispatchSignature;
  final String? deliverySignature;

  /// Lines of the stock receipt the franchise accepts on delivery.
  final List<ReceiptLine> receiptLines;

  const Order({
    required this.id,
    this.orderNumber,
    required this.franchiseId,
    required this.franchiseName,
    required this.items,
    required this.totalAmount,
    this.adjustedAmount,
    this.taxAmount = 0,
    required this.status,
    this.deliveryStatus = 'pending',
    this.declineReason,
    this.deliveryDeclinedReason,
    this.adjustmentNotes,
    this.expectedDeliveryDate,
    this.deliveredAt,
    this.createdAt,
    this.updatedAt,
    this.staffNotes,
    this.financeVerifiedBy,
    this.financeVerifiedAt,
    this.approvedBy,
    this.approvedAt,
    this.paymentVerifiedCount = 0,
    this.paymentTerms = 'credit',
    this.amountPaid = 0,
    this.dispatchMeans,
    this.dispatchVehicle,
    this.dispatchRemarks,
    this.dispatchedAt,
    this.approvalSignature,
    this.dispatchSignature,
    this.deliverySignature,
    this.receiptLines = const [],
  });

  static const Map<String, String> dispatchMeansLabels = {
    'truck': 'Truck',
    'bus': 'Bus',
    'pickup': 'Pick-up',
    'motorcycle': 'Motorcycle',
    'collected': 'Collected by franchise',
    'other': 'Other',
  };

  bool get isCashOrder => paymentTerms == 'cash';

  double get balanceDue {
    final due = totalAmount - amountPaid;
    return due > 0 ? due : 0;
  }

  String? get dispatchMeansLabel =>
      dispatchMeans == null ? null : (dispatchMeansLabels[dispatchMeans] ?? dispatchMeans);

  /// Dispatched (or arrived) but not yet accepted by the franchise.
  bool get awaitingFranchiseAcceptance =>
      status != 'declined' &&
      (deliveryStatus == 'out_for_delivery' || deliveryStatus == 'delivered');

  /// Credit orders can be dispatched once approved; cash orders need an
  /// accepted payment first.
  bool get canBeDispatched =>
      status == 'approved' &&
      !isOutForDelivery &&
      deliveryStatus != 'delivered' &&
      deliveryStatus != 'confirmed' &&
      (!isCashOrder || financeApproved);

  DeliveryStatus get deliveryStatusEnum {
    switch (deliveryStatus) {
      case 'payment_verified':
        return DeliveryStatus.paymentVerified;
      case 'ready_for_delivery':
        return DeliveryStatus.readyForDelivery;
      case 'out_for_delivery':
        return DeliveryStatus.outForDelivery;
      case 'delivered':
        return DeliveryStatus.delivered;
      case 'confirmed':
        return DeliveryStatus.confirmed;
      case 'delivery_declined':
        return DeliveryStatus.declined;
      default:
        return DeliveryStatus.pending;
    }
  }

  bool get isPaymentReady =>
      deliveryStatusEnum == DeliveryStatus.paymentVerified ||
      deliveryStatusEnum == DeliveryStatus.readyForDelivery;

  bool get isOutForDelivery =>
      deliveryStatusEnum == DeliveryStatus.outForDelivery;

  OrderStatus get statusEnum {
    if (deliveryStatusEnum == DeliveryStatus.delivered ||
        deliveryStatusEnum == DeliveryStatus.confirmed ||
        deliveredAt != null) {
      return OrderStatus.delivered;
    }
    switch (status) {
      case 'pending':
        return OrderStatus.pending;
      case 'approved':
        return OrderStatus.approved;
      case 'declined':
        return OrderStatus.declined;
      case 'adjusted':
        return OrderStatus.adjusted;
      case 'delivered':
        return OrderStatus.delivered;
      case 'cancelled':
        return OrderStatus.cancelled;
      default:
        return OrderStatus.pending;
    }
  }

  static DateTime? _parseDate(dynamic value) {
    if (value == null) return null;
    if (value is DateTime) return value;
    if (value is String && value.isNotEmpty) return DateTime.tryParse(value);
    return null;
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0;
    return 0;
  }

  static int _toInt(dynamic value) {
    if (value is bool) return value ? 1 : 0;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value) ?? 0;
    return 0;
  }

  static bool _isTruthy(dynamic value) {
    if (value is bool) return value;
    if (value is num) return value > 0;
    if (value is String) {
      return {
        '1',
        'true',
        'yes',
        'accepted',
        'approved',
        'verified',
      }.contains(value.toLowerCase());
    }
    return false;
  }

  factory Order.fromJson(Map<String, dynamic> json) {
    final franchise = json['franchise'];
    final payment = json['payment'];
    final financeAccepted =
        _isTruthy(
          json['payment_accepted'] ??
              json['finance_approved'] ??
              json['payment_status'],
        ) ||
        (payment is Map && _isTruthy(payment['accepted'] ?? payment['status']));
    return Order(
      id: json['id']?.toString() ?? '',
      orderNumber: json['order_number'],
      franchiseId: franchise is Map
          ? (franchise['id']?.toString() ??
                json['franchise_id']?.toString() ??
                '')
          : (json['franchise_id']?.toString() ?? ''),
      franchiseName: franchise is Map
          ? (franchise['name'] ?? '')
          : (json['franchise_name'] ?? ''),
      items:
          (json['items'] as List<dynamic>?)
              ?.map((i) => OrderItem.fromJson(i))
              .toList() ??
          [],
      totalAmount: _toDouble(json['total_amount']),
      adjustedAmount: json['adjusted_amount'] == null
          ? null
          : _toDouble(json['adjusted_amount']),
      taxAmount: _toDouble(json['tax_amount']),
      status: json['status'] ?? 'pending',
      deliveryStatus: json['delivery_status'] ?? 'pending',
      declineReason: json['decline_reason'],
      deliveryDeclinedReason:
          json['delivery_declined_reason'] ?? json['declined_reason'],
      adjustmentNotes: json['adjustment_notes'],
      expectedDeliveryDate: _parseDate(json['expected_delivery_date']),
      deliveredAt: _parseDate(
        json['delivered_at'] ?? json['completed_at'] ?? json['received_at'],
      ),
      createdAt: _parseDate(json['created_at']) ?? DateTime.now(),
      updatedAt: _parseDate(json['updated_at']) ?? DateTime.now(),
      staffNotes: json['notes'] ?? json['staff_notes'],
      financeVerifiedBy: json['finance_verified_by']?.toString(),
      financeVerifiedAt: _parseDate(json['finance_verified_at']),
      approvedBy: json['approved_by']?.toString(),
      approvedAt: _parseDate(json['approved_at']),
      paymentVerifiedCount: _toInt(
        json['payment_accepted_count'] ??
            json['payment_verified'] ??
            (financeAccepted ? 1 : 0),
      ),
      paymentTerms: json['payment_terms']?.toString() ?? 'credit',
      amountPaid: _toDouble(json['amount_paid']),
      dispatchMeans: json['dispatch_means']?.toString(),
      dispatchVehicle: json['dispatch_vehicle']?.toString(),
      dispatchRemarks: json['dispatch_remarks']?.toString(),
      dispatchedAt: _parseDate(json['dispatched_at']),
      approvalSignature: json['approval_signature']?.toString(),
      dispatchSignature: json['dispatch_signature']?.toString(),
      deliverySignature: json['delivery_signature']?.toString(),
      receiptLines: ReceiptLine.listFrom(json['stock_receipt']),
    );
  }

  /// True when Finance has fully ACCEPTED (approved) a payment for this order.
  bool get financeApproved =>
      paymentVerifiedCount > 0 ||
      isPaymentReady ||
      deliveryStatusEnum == DeliveryStatus.outForDelivery ||
      deliveryStatusEnum == DeliveryStatus.delivered ||
      statusEnum == OrderStatus.delivered;

  bool get isFullyCompleted =>
      statusEnum == OrderStatus.delivered ||
      deliveryStatusEnum == DeliveryStatus.delivered ||
      deliveryStatusEnum == DeliveryStatus.confirmed;

  bool get isPaymentComplete => isFullyCompleted || financeApproved;

  bool get isDispatchComplete => isFullyCompleted || isOutForDelivery;

  /// Payment status summary for this order.
  String get paymentStatusLabel {
    if (financeApproved) return 'Approved by Finance';
    if (deliveryStatusEnum == DeliveryStatus.pending) {
      return 'Awaiting Finance Approval';
    }
    return deliveryStatusEnum.displayName;
  }

  int get totalItems => items.fold(0, (sum, item) => sum + item.quantity);

  double get totalWithoutTax =>
      taxAmount > 0 ? totalAmount - taxAmount : totalAmount;

  double get displayedAmount => adjustedAmount ?? totalAmount;

  String get displayOrderNumber => orderNumber ?? id;
}

class OrderItem {
  final String id;
  final String productId;
  final String productName;
  final String categoryName;
  final String? imageUrl;
  final int quantity;
  final double unitPrice;
  final double baseUnitPrice;
  final double taxRate;
  final double taxAmount;
  final double totalPrice;
  final int? adjustedQuantity;
  final String? notes;

  const OrderItem({
    required this.id,
    required this.productId,
    required this.productName,
    required this.categoryName,
    this.imageUrl,
    required this.quantity,
    required this.unitPrice,
    this.baseUnitPrice = 0,
    this.taxRate = 0,
    this.taxAmount = 0,
    required this.totalPrice,
    this.adjustedQuantity,
    this.notes,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    final product = json['product'];
    return OrderItem(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString() ?? '',
      productName: product is Map
          ? (product['name'] ?? '')
          : (json['product_name'] ?? ''),
      categoryName: product is Map && product['category'] is Map
          ? (product['category']['name'] ?? '')
          : (json['category_name'] ?? ''),
      imageUrl: product is Map
          ? (product['image_url'] ?? product['image'] ?? json['image_url'])
          : json['image_url'],
      quantity: Order._toInt(json['quantity']),
      unitPrice: Order._toDouble(json['unit_price']),
      baseUnitPrice: Order._toDouble(
        json['base_unit_price'] ?? json['unit_price'],
      ),
      taxRate: Order._toDouble(json['tax_rate']),
      taxAmount: Order._toDouble(json['tax_amount']),
      totalPrice: Order._toDouble(json['subtotal'] ?? json['total_price']),
      adjustedQuantity: json['adjusted_quantity'] == null
          ? null
          : Order._toInt(json['adjusted_quantity']),
      notes: json['adjustment_notes'] ?? json['notes'],
    );
  }

  int get effectiveQuantity => adjustedQuantity ?? quantity;
}

/// One line of the stock receipt created when the warehouse approves an
/// order. The franchise confirms the received quantity per line.
class ReceiptLine {
  final String id;
  final String productName;
  final double dispatchedQuantity;
  final double receivedQuantity;

  const ReceiptLine({
    required this.id,
    required this.productName,
    required this.dispatchedQuantity,
    this.receivedQuantity = 0,
  });

  static double _num(dynamic v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }

  static List<ReceiptLine> listFrom(dynamic receipt) {
    if (receipt is! Map || receipt['items'] is! List) return const [];
    return (receipt['items'] as List)
        .whereType<Map>()
        .map(
          (i) => ReceiptLine(
            id: i['id']?.toString() ?? '',
            productName: i['product'] is Map
                ? (i['product']['name']?.toString() ?? '')
                : (i['product_name']?.toString() ?? ''),
            dispatchedQuantity: _num(i['ordered_quantity']),
            receivedQuantity: _num(i['received_quantity']),
          ),
        )
        .toList();
  }
}
