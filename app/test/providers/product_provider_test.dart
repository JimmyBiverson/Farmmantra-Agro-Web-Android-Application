import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/providers/product_provider.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';

void main() {
  late MockApiService apiService;
  late ProductProvider provider;

  setUp(() async {
    apiService = MockApiService();
    await apiService.initialize();
    provider = ProductProvider(apiService: apiService);
  });

  group('ProductProvider', () {
    test('initial state', () {
      expect(provider.products, isEmpty);
      expect(provider.categories, isEmpty);
      expect(provider.isLoading, false);
      expect(provider.isEmpty, true);
    });

    test('loadCategories populates categories', () async {
      await provider.loadCategories();
      expect(provider.categories, isNotEmpty);
      expect(provider.categories.length, 7);
    });

    test('loadProducts populates products', () async {
      await provider.loadProducts();
      expect(provider.products, isNotEmpty);
      expect(provider.products.length, 7);
      expect(provider.isLoading, false);
    });

    test('loadProducts filters by categoryId', () async {
      await provider.loadProducts(categoryId: '1');
      expect(provider.products.every((p) => p.categoryId == '1'), true);
    });

    test('filterProducts filters by name', () async {
      await provider.loadProducts();
      provider.filterProducts('roundup');
      expect(provider.products.length, 1);
      expect(provider.products.first.name, 'Roundup PowerMax');
    });

    test('filterProducts filters by category', () async {
      await provider.loadProducts();
      provider.filterProducts('Fungicides');
      expect(provider.products.length, 1);
      expect(provider.products.first.categoryName, 'Fungicides');
    });

    test('filterProducts with empty query shows all', () async {
      await provider.loadProducts();
      final allCount = provider.products.length;
      provider.filterProducts('something');
      provider.filterProducts('');
      expect(provider.products.length, allCount);
    });

    test('selectCategory loads products for that category', () async {
      await provider.loadCategories();
      await provider.loadProducts();
      final allCount = provider.products.length;

      provider.selectCategory('1');
      await Future.delayed(const Duration(seconds: 2));
      expect(provider.products.every((p) => p.categoryId == '1'), true);
      expect(provider.products.length, lessThan(allCount));
    });

    test('clearError clears the error', () async {
      await provider.loadProducts();
      provider.clearError();
      expect(provider.error, isNull);
    });

    test('isLoading is true during load', () async {
      bool wasLoading = false;
      provider.addListener(() {
        if (provider.isLoading) wasLoading = true;
      });
      await provider.loadProducts();
      expect(wasLoading, true);
      expect(provider.isLoading, false);
    });
  });
}
