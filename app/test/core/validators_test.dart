import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/core/utils/validators.dart';

void main() {
  group('Validators.required', () {
    test('returns error for null', () {
      expect(Validators.required(null), 'This field is required');
    });

    test('returns error for empty string', () {
      expect(Validators.required(''), 'This field is required');
    });

    test('returns error for whitespace only', () {
      expect(Validators.required('   '), 'This field is required');
    });

    test('returns null for valid input', () {
      expect(Validators.required('hello'), isNull);
    });

    test('uses custom field name', () {
      expect(Validators.required(null, 'Email'), 'Email is required');
    });
  });

  group('Validators.email', () {
    test('returns error for null', () {
      expect(Validators.email(null), 'Email is required');
    });

    test('returns error for empty', () {
      expect(Validators.email(''), 'Email is required');
    });

    test('returns error for invalid email', () {
      expect(Validators.email('notanemail'), 'Enter a valid email address');
      expect(Validators.email('test@'), 'Enter a valid email address');
      expect(Validators.email('@test.com'), 'Enter a valid email address');
    });

    test('returns null for valid email', () {
      expect(Validators.email('test@example.com'), isNull);
      expect(Validators.email('user.name@domain.co.ug'), isNull);
    });
  });

  group('Validators.phone', () {
    test('returns error for null', () {
      expect(Validators.phone(null), 'Phone number is required');
    });

    test('returns error for empty', () {
      expect(Validators.phone(''), 'Phone number is required');
    });

    test('returns error for too short', () {
      expect(Validators.phone('123'), 'Enter a valid phone number');
    });

    test('returns null for valid phone', () {
      expect(Validators.phone('0700000000'), isNull);
      expect(Validators.phone('+256700000000'), isNull);
      expect(Validators.phone('070 000 0000'), isNull);
    });
  });

  group('Validators.password', () {
    test('returns error for null', () {
      expect(Validators.password(null), 'Password is required');
    });

    test('returns error for empty', () {
      expect(Validators.password(''), 'Password is required');
    });

    test('returns error for too short', () {
      expect(
        Validators.password('abc'),
        'Password must be at least 6 characters',
      );
    });

    test('returns null for valid password', () {
      expect(Validators.password('password123'), isNull);
    });
  });

  group('Validators.confirmPassword', () {
    test('returns error for null', () {
      expect(
        Validators.confirmPassword(null, 'pass'),
        'Please confirm your password',
      );
    });

    test('returns error for mismatch', () {
      expect(
        Validators.confirmPassword('wrong', 'password'),
        'Passwords do not match',
      );
    });

    test('returns null for matching passwords', () {
      expect(Validators.confirmPassword('password', 'password'), isNull);
    });
  });

  group('Validators.number', () {
    test('returns error for null', () {
      expect(Validators.number(null), 'This field is required');
    });

    test('returns error for non-numeric', () {
      expect(Validators.number('abc'), 'Enter a valid number');
    });

    test('returns null for valid number', () {
      expect(Validators.number('42'), isNull);
      expect(Validators.number('3.14'), isNull);
    });
  });

  group('Validators.positiveNumber', () {
    test('returns error for zero', () {
      expect(
        Validators.positiveNumber('0'),
        'This field must be greater than 0',
      );
    });

    test('returns error for negative', () {
      expect(
        Validators.positiveNumber('-5'),
        'This field must be greater than 0',
      );
    });

    test('returns null for positive number', () {
      expect(Validators.positiveNumber('5'), isNull);
    });
  });

  group('Validators.minLength', () {
    test('returns error for null', () {
      expect(
        Validators.minLength(null, 3),
        'This field must be at least 3 characters',
      );
    });

    test('returns error for too short', () {
      expect(
        Validators.minLength('ab', 3),
        'This field must be at least 3 characters',
      );
    });

    test('returns null for sufficient length', () {
      expect(Validators.minLength('abc', 3), isNull);
    });
  });
}
