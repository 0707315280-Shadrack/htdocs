<article class="product-card-v3">

    <a
        href="/shop/product.php?id=<?= (int)$p['product_id'] ?>"
        class="product-photo"
    >

        <img
            loading="lazy"
            src="<?= e(product_image(
                $p['image_url'] ?? '',
                (string)($p['product_name'] ?? '')
            )) ?>"
            alt="<?= e($p['product_name'] ?? 'Product') ?>"
        >

        <?php
        $unitPrice = (float)($p['unit_price'] ?? 0);
        $comparePrice = (float)($p['compare_at_price'] ?? 0);
        $stock = (int)($p['quantity_in_stock'] ?? 0);
        ?>

        <?php if ($comparePrice > $unitPrice && $unitPrice > 0): ?>

            <?php
            $discount = round(
                (1 - ($unitPrice / $comparePrice)) * 100
            );
            ?>

            <span class="discount-badge">
                -<?= $discount ?>%
            </span>

        <?php endif; ?>

        <?php if (!empty($p['is_featured'])): ?>

            <span class="featured-badge">
                Featured
            </span>

        <?php endif; ?>

    </a>

    <div class="product-info">

        <span class="product-cat">
            <?= e($p['category_name'] ?? 'General') ?>
        </span>

        <a
            href="/shop/product.php?id=<?= (int)$p['product_id'] ?>"
            class="product-name"
        >
            <?= e($p['product_name'] ?? 'Product') ?>
        </a>

        <div class="rating-row">

            <span aria-hidden="true">★</span>

            <?= number_format(
                (float)($p['rating'] ?? 0),
                1
            ) ?>

            <small>
                (<?= number_format(
                    (int)($p['review_count'] ?? 0)
                ) ?>)
            </small>

        </div>

        <div class="price-row">

            <strong>
                <?= money($unitPrice) ?>
            </strong>

            <?php if ($comparePrice > $unitPrice): ?>

                <del>
                    <?= money($comparePrice) ?>
                </del>

            <?php endif; ?>

        </div>

        <div
            class="stock-line <?= $stock < 1 ? 'stock-out' : '' ?>"
        >

            <?php if ($stock > 0): ?>

                <span aria-hidden="true">✓</span>
                In stock

            <?php else: ?>

                Out of stock

            <?php endif; ?>

        </div>

        <?php if ($stock > 0): ?>

            <?php if (is_customer_logged_in()): ?>

                <form
                    method="post"
                    action="/shop/cart.php"
                    class="quick-add"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?= (int)$p['product_id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="quantity"
                        value="1"
                    >

                    <button
                        type="submit"
                        class="btn btn-cart"
                    >
                        Add to cart
                    </button>

                </form>

            <?php else: ?>

                <?php
                $productUrl =
                    '/shop/product.php?id=' .
                    (int)$p['product_id'];
                ?>

                <a
                    class="btn btn-cart guest-action"
                    href="/auth/customer-login.php?next=<?= rawurlencode($productUrl) ?>"
                >
                    Sign in to shop
                </a>

            <?php endif; ?>

        <?php else: ?>

            <button
                type="button"
                class="btn btn-cart"
                disabled
            >
                Currently unavailable
            </button>

        <?php endif; ?>

    </div>

</article>