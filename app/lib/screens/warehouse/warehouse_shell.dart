import 'package:flutter/material.dart';
import '../../features/operations/presentation/operations_home.dart';

/// WarehouseShell owns the role entry point; shared workflows live in features/operations.
class WarehouseShell extends StatelessWidget {
  const WarehouseShell({super.key});
  @override
  Widget build(BuildContext context) => const OperationsShell();
}
