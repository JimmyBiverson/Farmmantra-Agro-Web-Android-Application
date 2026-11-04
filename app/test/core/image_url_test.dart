import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/core/utils/image_url.dart';

void main() {
  group('resolveImageUrl', () {
    test('returns external URLs rewritten to base host for local dev', () {
      expect(
        resolveImageUrl('https://cdn.example.com/p.png'),
        'http://10.0.2.2:8000/p.png',
      );
      expect(
        resolveImageUrl('http://localhost/x.png'),
        'http://10.0.2.2:8000/x.png',
      );
    });

    test('builds full URL for relative storage paths', () {
      expect(
        resolveImageUrl('products/SEED-001.png'),
        'http://10.0.2.2:8000/storage/products/SEED-001.png',
      );
    });

    test('preserves leading-slash paths', () {
      expect(
        resolveImageUrl('/storage/avatars/a.png'),
        'http://10.0.2.2:8000/storage/avatars/a.png',
      );
    });

    test('handles null and empty values', () {
      expect(resolveImageUrl(null), '');
      expect(resolveImageUrl(''), '');
      expect(resolveImageUrl('   '), '');
    });
  });
}
