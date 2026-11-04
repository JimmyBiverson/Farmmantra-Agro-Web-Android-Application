import 'package:farmmantra/core/constants/api_endpoints.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/core/utils/image_url.dart';

void main() {
  group('resolveImageUrl', () {
    test('returns external URLs rewritten to base host for local dev', () {
      expect(
        resolveImageUrl('https://cdn.example.com/p.png'),
        '${Uri.parse(ApiEndpoints.baseUrl).origin}/p.png',
      );
      expect(
        resolveImageUrl('http://localhost/x.png'),
        '${Uri.parse(ApiEndpoints.baseUrl).origin}/x.png',
      );
    });

    test('builds full URL for relative storage paths', () {
      expect(
        resolveImageUrl('products/SEED-001.png'),
        '${Uri.parse(ApiEndpoints.baseUrl).origin}/storage/products/SEED-001.png',
      );
    });

    test('preserves leading-slash paths', () {
      expect(
        resolveImageUrl('/storage/avatars/a.png'),
        '${Uri.parse(ApiEndpoints.baseUrl).origin}/storage/avatars/a.png',
      );
    });

    test('handles null and empty values', () {
      expect(resolveImageUrl(null), '');
      expect(resolveImageUrl(''), '');
      expect(resolveImageUrl('   '), '');
    });
  });
}
