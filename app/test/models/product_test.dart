import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/models/product.dart';
import 'package:farmmantra/core/constants/api_endpoints.dart';

void main() {
  group('ProductCategory', () {
    test('fromJson parses valid JSON', () {
      final json = {
        'id': '5',
        'name': 'Seeds',
        'description': 'All seeds',
        'icon_url': 'https://img.test/seed.png',
        'product_count': 12,
      };
      final cat = ProductCategory.fromJson(json);

      expect(cat.id, '5');
      expect(cat.name, 'Seeds');
      expect(cat.description, 'All seeds');
      expect(cat.iconUrl, 'https://img.test/seed.png');
      expect(cat.productCount, 12);
    });

    test('fromJson handles missing fields', () {
      final cat = ProductCategory.fromJson({});
      expect(cat.id, '');
      expect(cat.name, '');
      expect(cat.productCount, 0);
    });

    test('defaultCategories has 7 items', () {
      expect(ProductCategory.defaultCategories.length, 7);
    });
  });

  group('Product', () {
    test('uses backend delivery URLs instead of raw upload paths', () {
      final origin = Uri.parse(ApiEndpoints.baseUrl).origin;
      final image = '$origin/product-asset/products/cover.png';
      final gallery = '$origin/product-asset/products/gallery.png';
      final product = Product.fromJson({
        'image': 'products/cover.png',
        'image_url': image,
        'images': [
          {'image_path': 'products/gallery.png', 'image_url': gallery},
        ],
        'all_images': [image, gallery],
      });
      expect(product.imageUrl, image);
      expect(product.imageUrls.toSet(), {image, gallery});
      expect(product.imageUrls.length, 2);
    });

    final product = Product(
      id: '1',
      name: 'Roundup',
      categoryId: '1',
      categoryName: 'Herbicides',
      unitOfMeasure: 'Litres',
      standardPrice: 45000,
      priceSlabs: const [
        PriceSlab(
          id: 's1',
          productId: '1',
          minQuantity: 1,
          maxQuantity: 9,
          pricePerUnit: 45000,
        ),
        PriceSlab(
          id: 's2',
          productId: '1',
          minQuantity: 10,
          maxQuantity: 49,
          pricePerUnit: 42000,
        ),
        PriceSlab(
          id: 's3',
          productId: '1',
          minQuantity: 50,
          pricePerUnit: 38000,
        ),
      ],
    );

    test('fromJson parses product with slabs', () {
      final json = {
        'id': '1',
        'name': 'Roundup',
        'category_id': '1',
        'category_name': 'Herbicides',
        'unit_of_measure': 'Litres',
        'standard_price': 45000,
        'is_active': true,
      };
      final p = Product.fromJson(json);
      expect(p.id, '1');
      expect(p.name, 'Roundup');
      expect(p.standardPrice, 45000);
    });

    test('getPriceForQuantity returns standard price when no slabs', () {
      final noSlabs = Product(
        id: '2',
        name: 'Basic',
        categoryId: '1',
        categoryName: 'Cat',
        unitOfMeasure: 'Kg',
        standardPrice: 10000,
      );
      expect(noSlabs.getPriceForQuantity(5), 10000);
    });

    test('getPriceForQuantity returns correct slab price for small qty', () {
      expect(product.getPriceForQuantity(5), 45000);
    });

    test('getPriceForQuantity returns correct slab price for medium qty', () {
      expect(product.getPriceForQuantity(20), 42000);
    });

    test('getPriceForQuantity returns correct slab price for large qty', () {
      expect(product.getPriceForQuantity(100), 38000);
    });

    test('getApplicableSlab returns null when no slabs defined', () {
      final noSlabs = Product(
        id: '2',
        name: 'Basic',
        categoryId: '1',
        categoryName: 'Cat',
        unitOfMeasure: 'Kg',
        standardPrice: 10000,
      );
      expect(noSlabs.getApplicableSlab(5), isNull);
    });

    test('getApplicableSlab returns correct slab', () {
      final slab = product.getApplicableSlab(20);
      expect(slab, isNotNull);
      expect(slab!.pricePerUnit, 42000);
    });
  });

  group('PriceSlab', () {
    test('displayLabel with label returns label', () {
      const slab = PriceSlab(
        id: '1',
        productId: '1',
        minQuantity: 10,
        maxQuantity: 49,
        pricePerUnit: 42000,
        label: 'Bulk Rate',
      );
      expect(slab.displayLabel, 'Bulk Rate');
    });

    test('displayLabel with maxQuantity returns range', () {
      const slab = PriceSlab(
        id: '1',
        productId: '1',
        minQuantity: 10,
        maxQuantity: 49,
        pricePerUnit: 42000,
      );
      expect(slab.displayLabel, '10 - 49 units');
    });

    test('displayLabel without maxQuantity returns open-ended', () {
      const slab = PriceSlab(
        id: '1',
        productId: '1',
        minQuantity: 50,
        pricePerUnit: 38000,
      );
      expect(slab.displayLabel, '50+ units');
    });
  });
}
