<section class="sitara-page-heading">
    <div>
        <h1><?= esc($pageTitle ?? 'SITARA') ?></h1>
        <p><?= esc($pageDescription ?? $pageSubtitle ?? '') ?></p>
    </div>
    <?php if (! empty($pageActionLabel) && ! empty($pageActionUrl)): ?>
        <a href="<?= esc($pageActionUrl) ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center">
            <?= esc($pageActionLabel) ?>
        </a>
    <?php endif; ?>
</section>
