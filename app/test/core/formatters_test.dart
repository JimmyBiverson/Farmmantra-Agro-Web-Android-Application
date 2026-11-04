import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/core/utils/formatters.dart';

void main() {
  group('Formatters.currency', () {
    test('formats number with UGX prefix', () {
      expect(Formatters.currency(45000), 'UGX 45,000');
    });

    test('formats string number', () {
      expect(Formatters.currency('100000'), 'UGX 100,000');
    });

    test('returns UGX 0 for null', () {
      expect(Formatters.currency(null), 'UGX 0');
    });

    test('returns UGX 0 for unparseable string', () {
      expect(Formatters.currency('abc'), 'UGX 0');
    });
  });

  group('Formatters.currencyPlain', () {
    test('formats without UGX prefix', () {
      expect(Formatters.currencyPlain(45000), '45,000');
    });

    test('returns 0 for null', () {
      expect(Formatters.currencyPlain(null), '0');
    });
  });

  group('Formatters.decimal', () {
    test('formats with 2 decimal places', () {
      expect(Formatters.decimal(3.14159), '3.14');
    });

    test('returns 0.00 for null', () {
      expect(Formatters.decimal(null), '0.00');
    });
  });

  group('Formatters.date', () {
    test('formats DateTime correctly', () {
      final dt = DateTime(2024, 3, 15);
      expect(Formatters.date(dt), '15 Mar 2024');
    });

    test('formats ISO string', () {
      expect(Formatters.date('2024-01-01T00:00:00.000Z'), isNotEmpty);
    });

    test('returns empty for null', () {
      expect(Formatters.date(null), '');
    });

    test('returns empty for invalid string', () {
      expect(Formatters.date('not-a-date'), '');
    });
  });

  group('Formatters.dateTime', () {
    test('formats DateTime with time', () {
      final dt = DateTime(2024, 3, 15, 14, 30);
      final result = Formatters.dateTime(dt);
      expect(result, contains('15 Mar 2024'));
    });

    test('returns empty for null', () {
      expect(Formatters.dateTime(null), '');
    });
  });

  group('Formatters.percentage', () {
    test('formats percentage', () {
      expect(Formatters.percentage(62.5), '62.50%');
    });

    test('returns 0% for null', () {
      expect(Formatters.percentage(null), '0%');
    });
  });

  group('Formatters.quantity', () {
    test('formats integer without decimals', () {
      expect(Formatters.quantity(50), '50');
    });

    test('formats decimal with 2 places', () {
      expect(Formatters.quantity(5.5), '5.50');
    });

    test('returns 0 for null', () {
      expect(Formatters.quantity(null), '0');
    });
  });

  group('Formatters.shortDate', () {
    test('formats as dd/MM/yyyy', () {
      final dt = DateTime(2024, 1, 5);
      expect(Formatters.shortDate(dt), '05/01/2024');
    });
  });

  group('Formatters.monthYear', () {
    test('formats as MMM yyyy', () {
      final dt = DateTime(2024, 3);
      expect(Formatters.monthYear(dt), 'Mar 2024');
    });
  });
}
