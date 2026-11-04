import 'package:flutter/material.dart';
import '../../core/utils/responsive.dart';

class AdaptiveLayout extends StatelessWidget {
  final Widget mobile;
  final Widget? tablet;
  final Widget? desktop;

  const AdaptiveLayout({
    super.key,
    required this.mobile,
    this.tablet,
    this.desktop,
  });

  @override
  Widget build(BuildContext context) {
    if (Responsive.isDesktop(context) && desktop != null) {
      return desktop!;
    }
    if (Responsive.isTablet(context) && tablet != null) {
      return tablet!;
    }
    return mobile;
  }
}

class ResponsiveBuilder extends StatelessWidget {
  final Widget Function(BuildContext context, BoxConstraints constraints)
  builder;

  const ResponsiveBuilder({super.key, required this.builder});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: builder);
  }
}

class AdaptiveGrid extends StatelessWidget {
  final List<Widget> children;
  final double spacing;
  final double runSpacing;
  final double? maxChildExtent;

  const AdaptiveGrid({
    super.key,
    required this.children,
    this.spacing = 16.0,
    this.runSpacing = 16.0,
    this.maxChildExtent,
  });

  @override
  Widget build(BuildContext context) {
    final columns = Responsive.gridColumns(context);
    return GridView.count(
      crossAxisCount: columns,
      mainAxisSpacing: runSpacing,
      crossAxisSpacing: spacing,
      childAspectRatio: maxChildExtent != null ? -1 : 1.5,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: children,
    );
  }
}

class AdaptiveWidthContainer extends StatelessWidget {
  final Widget child;
  final double maxWidth;

  const AdaptiveWidthContainer({
    super.key,
    required this.child,
    this.maxWidth = 600.0,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}
