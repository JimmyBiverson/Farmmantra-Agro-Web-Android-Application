import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/models/user.dart';
import 'package:farmmantra/core/enums/user_role.dart';

void main() {
  group('User', () {
    test('fromJson parses valid JSON', () {
      final json = {
        'id': '42',
        'name': 'Jane Doe',
        'email': 'jane@test.com',
        'phone': '+256700000000',
        'role': 'franchisePartner',
        'franchise_id': 'F001',
        'franchise_name': 'Kampala',
        'avatar_url': 'https://img.test/a.png',
        'created_at': '2024-01-15T10:30:00.000Z',
        'is_active': true,
      };
      final user = User.fromJson(json);

      expect(user.id, '42');
      expect(user.name, 'Jane Doe');
      expect(user.email, 'jane@test.com');
      expect(user.phone, '+256700000000');
      expect(user.role, UserRole.franchisePartner);
      expect(user.franchiseId, 'F001');
      expect(user.franchiseName, 'Kampala');
      expect(user.avatarUrl, 'http://10.0.2.2:8000/a.png');
      expect(user.createdAt, isNotNull);
      expect(user.isActive, true);
    });

    test('fromJson handles missing fields gracefully', () {
      final json = <String, dynamic>{};
      final user = User.fromJson(json);

      expect(user.id, '');
      expect(user.name, '');
      expect(user.email, '');
      expect(user.role, UserRole.franchisePartner);
      expect(user.franchiseId, isNull);
      expect(user.isActive, true);
    });

    test('fromJson parses unknown role as franchisePartner', () {
      final json = {
        'id': '1',
        'name': 'Test',
        'email': 't@t.com',
        'phone': '123',
        'role': 'unknown_role',
      };
      final user = User.fromJson(json);
      expect(user.role, UserRole.franchisePartner);
    });

    test('toJson produces correct map', () {
      final user = User(
        id: '1',
        name: 'Test',
        email: 't@t.com',
        phone: '123',
        role: UserRole.farmmantraStaff,
        createdAt: DateTime(2024, 1, 1),
      );
      final json = user.toJson();

      expect(json['id'], '1');
      expect(json['name'], 'Test');
      expect(json['role'], 'farmmantraStaff');
      expect(json['created_at'], isNotNull);
    });

    test('copyWith preserves unchanged fields', () {
      final user = User(
        id: '1',
        name: 'Original',
        email: 'o@o.com',
        phone: '123',
        role: UserRole.financeDepartment,
      );

      final updated = user.copyWith(name: 'Updated');

      expect(updated.id, '1');
      expect(updated.name, 'Updated');
      expect(updated.email, 'o@o.com');
      expect(updated.role, UserRole.financeDepartment);
    });

    test('initials returns two-letter initials for multi-word names', () {
      final user = User(
        id: '1',
        name: 'John Doe',
        email: 'j@j.com',
        phone: '123',
        role: UserRole.franchisePartner,
      );
      expect(user.initials, 'JD');
    });

    test('initials returns single letter for single-word name', () {
      final user = User(
        id: '1',
        name: 'Madonna',
        email: 'm@m.com',
        phone: '123',
        role: UserRole.franchisePartner,
      );
      expect(user.initials, 'M');
    });

    test('initials returns ? for empty name', () {
      final user = User(
        id: '1',
        name: '',
        email: 'e@e.com',
        phone: '123',
        role: UserRole.franchisePartner,
      );
      expect(user.initials, '?');
    });

    test('fromJson parses various roles correctly', () {
      for (final role in UserRole.values) {
        final json = {
          'id': '1',
          'name': 'Test',
          'email': 't@t.com',
          'phone': '123',
          'role': role.name,
        };
        final user = User.fromJson(json);
        expect(user.role, role);
      }
    });
  });
}
