import '../core/enums/app_enums.dart';

class InventoryItem {
  final String id;
  final String productId;
  final String productName;
  final String categoryName;
  final double quantity;
  final String unitOfMeasure;
  final double unitCost;
  final double totalValue;
  final String? franchiseId;
  final String? franchiseName;
  final double reorderLevel;
  final InventoryAlertLevel alertLevel;
  final dynamic lastUpdated;

  const InventoryItem({
    required this.id,
    required this.productId,
    required this.productName,
    required this.categoryName,
    required this.quantity,
    required this.unitOfMeasure,
    required this.unitCost,
    required this.totalValue,
    this.franchiseId,
    this.franchiseName,
    required this.reorderLevel,
    required this.alertLevel,
    required this.lastUpdated,
  });

  factory InventoryItem.fromJson(Map<String, dynamic> json) {
    final product = json['product'];
    final qty = _toDouble(json['quantity']);
    final reorder = _toDouble(json['reorder_level']);
    return InventoryItem(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString() ?? '',
      productName: product is Map
          ? (product['name'] ?? '')
          : (json['product_name'] ?? ''),
      categoryName: product is Map && product['category'] is Map
          ? (product['category']['name'] ?? '')
          : (json['category_name'] ?? ''),
      quantity: qty,
      unitOfMeasure: product is Map
          ? (product['unit_of_measure'] ?? '')
          : (json['unit_of_measure'] ?? ''),
      unitCost: product is Map
          ? _toDouble(product['standard_price'])
          : _toDouble(json['unit_cost']),
      totalValue: _toDouble(json['total_value']),
      franchiseId: json['franchise_id']?.toString(),
      franchiseName: json['franchise'] is Map
          ? (json['franchise']['name'] as String?)
          : (json['franchise_name'] as String?),
      reorderLevel: reorder,
      alertLevel: _parseAlertLevel(json['alert_level'], qty, reorder),
      lastUpdated: json['last_updated'] ?? json['updated_at'] ?? '',
    );
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0;
    return 0;
  }

  static InventoryAlertLevel _parseAlertLevel(
    dynamic level,
    double qty,
    double reorder,
  ) {
    if (level != null) {
      switch (level.toString()) {
        case 'normal':
          return InventoryAlertLevel.normal;
        case 'low':
          return InventoryAlertLevel.low;
        case 'critical':
          return InventoryAlertLevel.critical;
        case 'outOfStock':
        case 'out_of_stock':
          return InventoryAlertLevel.outOfStock;
      }
    }
    if (qty <= 0) return InventoryAlertLevel.outOfStock;
    if (reorder > 0 && qty <= reorder * 0.5) {
      return InventoryAlertLevel.critical;
    }
    if (qty <= reorder) return InventoryAlertLevel.low;
    return InventoryAlertLevel.normal;
  }

  bool get isLowStock => quantity <= reorderLevel && quantity > 0;
  bool get isOutOfStock => quantity <= 0;
}

class InventoryMovement {
  final String id;
  final String productName;
  final String type;
  final double quantity;
  final double previousQuantity;
  final double newQuantity;
  final String? referenceId;
  final String? notes;
  final dynamic createdAt;

  const InventoryMovement({
    required this.id,
    required this.productName,
    required this.type,
    required this.quantity,
    required this.previousQuantity,
    required this.newQuantity,
    this.referenceId,
    this.notes,
    required this.createdAt,
  });

  factory InventoryMovement.fromJson(Map<String, dynamic> json) {
    return InventoryMovement(
      id: json['id']?.toString() ?? '',
      productName: json['product'] is Map
          ? (json['product']['name'] ?? '')
          : (json['product_name'] ?? ''),
      type: json['type'] ?? '',
      quantity: InventoryItem._toDouble(json['quantity']).abs(),
      previousQuantity: InventoryItem._toDouble(json['previous_quantity']),
      newQuantity: InventoryItem._toDouble(json['new_quantity']),
      referenceId: json['reference_id']?.toString(),
      notes: json['notes'],
      createdAt: json['created_at'] ?? '',
    );
  }

  bool get isInbound => type.contains('in') || type.contains('received');
  bool get isOutbound => type.contains('out') || type.contains('sale');
}
