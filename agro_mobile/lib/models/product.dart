import '../core/utils/image_url.dart';

class ProductCategory {
  final String id;
  final String name;
  final String? description;
  final String? iconUrl;
  final int productCount;

  const ProductCategory({
    required this.id,
    required this.name,
    this.description,
    this.iconUrl,
    this.productCount = 0,
  });

  factory ProductCategory.fromJson(Map<String, dynamic> json) {
    return ProductCategory(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      description: json['description'],
      iconUrl: json['icon_url'] ?? json['image'],
      productCount: (json['product_count'] ?? 0) is String
          ? int.tryParse(json['product_count'].toString()) ?? 0
          : (json['product_count'] ?? 0),
    );
  }

  static const List<ProductCategory> defaultCategories = [
    ProductCategory(id: '1', name: 'Herbicides', productCount: 0),
    ProductCategory(id: '2', name: 'Insecticides', productCount: 0),
    ProductCategory(id: '3', name: 'Fungicides', productCount: 0),
    ProductCategory(id: '4', name: 'Organic Products', productCount: 0),
    ProductCategory(id: '5', name: 'Seeds', productCount: 0),
    ProductCategory(id: '6', name: 'Fertilizers', productCount: 0),
    ProductCategory(id: '7', name: 'PGR', productCount: 0),
  ];
}

class Product {
  final String id;
  final String name;
  final String? description;
  final String categoryId;
  final String categoryName;
  final String unitOfMeasure;
  final String? packagingDetails;
  final double standardPrice;
  final bool taxEnabled;
  final String? taxType;
  final double taxRate;
  final double basePrice;
  final double finalPrice;

  /// Admin-fixed selling prices. Franchises buy at [standardPrice] and may
  /// only sell at one of these two prices.
  final double retailPrice;
  final double farmerPrice;
  final List<PriceSlab> priceSlabs;
  final String? imageUrl;
  final List<String> imageUrls;
  final bool isActive;
  final DateTime? createdAt;

  const Product({
    required this.id,
    required this.name,
    this.description,
    required this.categoryId,
    required this.categoryName,
    required this.unitOfMeasure,
    this.packagingDetails,
    required this.standardPrice,
    this.taxEnabled = false,
    this.taxType,
    this.taxRate = 0,
    this.basePrice = 0,
    this.finalPrice = 0,
    this.retailPrice = 0,
    this.farmerPrice = 0,
    this.priceSlabs = const [],
    this.imageUrl,
    this.imageUrls = const [],
    this.isActive = true,
    this.createdAt,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    final category = json['category'];

    final rawSingleImg = json['image'] ?? json['image_url'];
    final mainImg = rawSingleImg != null ? resolveImageUrl(rawSingleImg) : null;

    final List<String> extractedUrls = [];

    if (json['images'] is List) {
      for (final imgItem in json['images']) {
        if (imgItem is Map) {
          final path = imgItem['image_path'] ?? imgItem['image_url'] ?? imgItem['url'];
          if (path != null) {
            final resolved = resolveImageUrl(path);
            if (resolved.isNotEmpty && !extractedUrls.contains(resolved)) {
              extractedUrls.add(resolved);
            }
          }
        } else if (imgItem is String && imgItem.isNotEmpty) {
          final resolved = resolveImageUrl(imgItem);
          if (resolved.isNotEmpty && !extractedUrls.contains(resolved)) {
            extractedUrls.add(resolved);
          }
        }
      }
    }

    if (json['all_images'] is List) {
      for (final imgItem in json['all_images']) {
        if (imgItem != null && imgItem.toString().isNotEmpty) {
          final resolved = resolveImageUrl(imgItem);
          if (resolved.isNotEmpty && !extractedUrls.contains(resolved)) {
            extractedUrls.add(resolved);
          }
        }
      }
    }

    if (mainImg != null && mainImg.isNotEmpty && !extractedUrls.contains(mainImg)) {
      extractedUrls.insert(0, mainImg);
    }

    return Product(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      description: json['description'],
      categoryId: category is Map
          ? (category['id']?.toString() ?? json['category_id']?.toString() ?? '')
          : (json['category_id']?.toString() ?? ''),
      categoryName: category is Map
          ? (category['name'] ?? '')
          : (json['category_name'] ?? ''),
      unitOfMeasure: json['unit_of_measure'] ?? '',
      packagingDetails: json['packaging_details'],
      standardPrice: _toDouble(json['standard_price'] ?? json['selling_price']),
      taxEnabled: json['tax_enabled'] ?? false,
      taxType: json['tax_type'],
      taxRate: _toDouble(json['tax_rate']),
      basePrice: _toDouble(json['base_price'] ?? json['standard_price']),
      finalPrice: _toDouble(json['final_price'] ?? json['effective_price'] ?? json['standard_price']),
      retailPrice: _toDouble(json['retail_price'] ?? json['selling_price'] ?? json['standard_price']),
      farmerPrice: _toDouble(json['farmer_unit_price'] ?? json['farmer_price'] ?? json['retail_price'] ?? json['selling_price'] ?? json['standard_price']),
      priceSlabs: (json['price_slabs'] as List<dynamic>?)
              ?.map((s) => PriceSlab.fromJson(s))
              .toList() ??
          [],
      imageUrl: mainImg ?? (extractedUrls.isNotEmpty ? extractedUrls.first : null),
      imageUrls: extractedUrls,
      isActive: json['is_active'] ?? true,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
    );
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0;
    return 0;
  }

  PriceSlab? getApplicableSlab(int quantity) {
    if (priceSlabs.isEmpty) return null;
    PriceSlab? applicable;
    for (final slab in priceSlabs) {
      if (quantity >= slab.minQuantity) {
        applicable = slab;
      }
    }
    return applicable;
  }

  /// Selling price for a customer type ('retailer' or 'farmer').
  double sellingPriceFor(String customerType) {
    final price = customerType == 'retailer' ? retailPrice : farmerPrice;
    return price > 0 ? price : standardPrice;
  }

  double getPriceForQuantity(int quantity) {
    final slab = getApplicableSlab(quantity);
    return slab?.pricePerUnit ?? standardPrice;
  }
}

class PriceSlab {
  final String id;
  final String productId;
  final int minQuantity;
  final int? maxQuantity;
  final double pricePerUnit;
  final String? label;

  const PriceSlab({
    required this.id,
    required this.productId,
    required this.minQuantity,
    this.maxQuantity,
    required this.pricePerUnit,
    this.label,
  });

  factory PriceSlab.fromJson(Map<String, dynamic> json) {
    return PriceSlab(
      id: json['id']?.toString() ?? '',
      productId: json['product_id']?.toString() ?? '',
      minQuantity: _toInt(json['min_quantity']),
      maxQuantity: json['max_quantity'] == null
          ? null
          : _toInt(json['max_quantity']),
      pricePerUnit:
          Product._toDouble(json['slab_price'] ?? json['price_per_unit']),
      label: json['label'],
    );
  }

  static int _toInt(dynamic value) {
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value) ?? 0;
    return 0;
  }

  String get displayLabel {
    if (label != null) return label!;
    if (maxQuantity != null) {
      return '$minQuantity - $maxQuantity units';
    }
    return '$minQuantity+ units';
  }
}
