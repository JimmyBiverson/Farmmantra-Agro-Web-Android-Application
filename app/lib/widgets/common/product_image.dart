import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/image_url.dart';

class ProductImage extends StatelessWidget {
  final String? imageUrl;
  final String? productName;
  final double? width;
  final double? height;
  final BoxFit fit;
  final double borderRadius;
  final Color? backgroundColor;

  const ProductImage({
    super.key,
    this.imageUrl,
    this.productName,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius = 8,
    this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    final rawUrl = imageUrl;
    final url = rawUrl != null ? resolveImageUrl(rawUrl) : null;
    final hasImage = url != null && url.isNotEmpty;

    final containerBg = backgroundColor ?? AppColors.backgroundLight;

    final fallback = Container(
      width: width,
      height: height,
      color: AppColors.primaryGreen.withAlpha(15),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.inventory_2_outlined,
              size: ((height ?? 48) * 0.35).clamp(18.0, 48.0),
              color: AppColors.primaryGreen.withAlpha(120),
            ),
          ],
        ),
      ),
    );

    int? cacheW;
    int? cacheH;
    if (width != null && width! > 0 && width! < 1200) {
      cacheW = (width! * 2).toInt();
    }
    if (height != null && height! > 0 && height! < 1200) {
      cacheH = (height! * 2).toInt();
    }

    Widget content;

    if (!hasImage) {
      content = fallback;
    } else {
      content = Image.network(
        url,
        width: width,
        height: height,
        cacheWidth: cacheW,
        cacheHeight: cacheH,
        fit: fit,
        errorBuilder: (context, error, stackTrace) => fallback,
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) return child;
          return Container(
            width: width,
            height: height,
            color: containerBg,
            child: Center(
              child: SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: AppColors.primaryGreen.withAlpha(120),
                ),
              ),
            ),
          );
        },
      );
    }

    Widget result = Container(
      width: width,
      height: height,
      color: containerBg,
      child: content,
    );

    if (borderRadius > 0) {
      result = ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius),
        child: result,
      );
    }

    return result;
  }
}
