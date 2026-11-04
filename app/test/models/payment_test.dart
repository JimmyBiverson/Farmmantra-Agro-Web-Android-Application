import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/models/payment.dart';

void main() {
  group('Payment', () {
    test('fromJson parses payment correctly', () {
      final json = {
        'id': 'PAY-5001',
        'payment_number': 'PAY-5001',
        'franchise_id': 'F001',
        'franchise_name': 'Kampala',
        'amount': 2000000,
        'transaction_reference': 'REF-001',
        'bank_name': 'Stanbic',
        'payment_method': 'Bank Transfer',
        'status': 'pending',
        'proof_of_payment_path': 'https://img.test/proof.png',
        'submitted_at': '2024-01-15T10:00:00.000Z',
        'updated_at': '2024-01-15T10:00:00.000Z',
      };
      final payment = Payment.fromJson(json);

      expect(payment.id, 'PAY-5001');
      expect(payment.amount, 2000000);
      expect(payment.transactionReference, 'REF-001');
      expect(payment.bankName, 'Stanbic');
      expect(payment.status, 'pending');
      expect(payment.proofUrl, 'https://img.test/proof.png');
    });

    test('fromJson handles missing fields', () {
      final p = Payment.fromJson({});
      expect(p.id, '');
      expect(p.amount, 0);
      expect(p.transactionReference, '');
      expect(p.status, 'pending');
    });

    test('statusEnum maps all statuses correctly', () {
      expect(
        const Payment(
          id: '',
          franchiseId: '',
          franchiseName: '',
          amount: 0,
          transactionReference: '',
          status: 'pending',
          submittedAt: '',
          updatedAt: '',
        ).statusEnum.name,
        'pending',
      );
      expect(
        const Payment(
          id: '',
          franchiseId: '',
          franchiseName: '',
          amount: 0,
          transactionReference: '',
          status: 'verified',
          submittedAt: '',
          updatedAt: '',
        ).statusEnum.name,
        'verified',
      );
      expect(
        const Payment(
          id: '',
          franchiseId: '',
          franchiseName: '',
          amount: 0,
          transactionReference: '',
          status: 'accepted',
          submittedAt: '',
          updatedAt: '',
        ).statusEnum.name,
        'accepted',
      );
      expect(
        const Payment(
          id: '',
          franchiseId: '',
          franchiseName: '',
          amount: 0,
          transactionReference: '',
          status: 'rejected',
          submittedAt: '',
          updatedAt: '',
        ).statusEnum.name,
        'rejected',
      );
    });
  });

  group('AccountSummary', () {
    test('fromJson parses correctly', () {
      final json = {
        'total_sales': 100000,
        'total_payments': 80000,
        'outstanding_balance': 20000,
        'credit_limit': 50000,
      };
      final s = AccountSummary.fromJson(json);
      expect(s.totalSales, 100000);
      expect(s.outstandingBalance, 20000);
    });

    test('creditUtilization computes correctly', () {
      const s = AccountSummary(
        totalSales: 100,
        totalPayments: 80,
        outstandingBalance: 20,
        creditLimit: 100,
      );
      expect(s.creditUtilization, 20.0);
    });

    test('creditUtilization returns 0 when creditLimit is 0', () {
      const s = AccountSummary(
        totalSales: 0,
        totalPayments: 0,
        outstandingBalance: 50,
        creditLimit: 0,
      );
      expect(s.creditUtilization, 0);
    });
  });
}
