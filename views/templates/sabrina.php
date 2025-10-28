<?php

declare(strict_types=1);

/**
 * Page “Sabrina” (vue PHP)
 * - Version statique (HTML + classes CSS)
 * - Carousel léger en CSS/scroll (pas de React)
 * - Les boutons pointent vers les routes existantes (inscription/connexion)
 *
 * ATTENTION aux chemins d’images :
 *   - Ici, j’utilise des images dans /public/assets/images/nadia/
 *   - Adapte les noms si besoin (slide1/2/3.jpg, hero.jpg)
 */
?>
<section class="sabrina-page">

    <!-- En-tête -->
    <header class="sabrina-header">
        <h1>Sabrina</h1>
        <p>Coach Paris-92-95 — coaching personnalisé et cours de fitness.</p>
    </header>

   
  

    <section class="sabrina-carousel">
        <h2>Galerie</h2>

        <div class="carousel" id="sabrinaCarousel" tabindex="0" aria-label="Galerie de photos de Sabrina">
            <div class="carousel-viewport">
                <div class="carousel-track">
                    <?php foreach (($gallery ?? []) as $item):
                        $src     = BASE_URL . ltrim((string)($item['src'] ?? ''), '/');
                        $alt     = htmlspecialchars($item['alt'] ?? 'demo Sabrina', ENT_QUOTES, 'UTF-8');
                        $caption = htmlspecialchars($item['caption'] ?? '', ENT_QUOTES, 'UTF-8');
                    ?>
                        <figure class="carousel-slide">
                            <img loading="lazy" src="<?= $src ?>" alt="<?= $alt ?>">
                            <?php if ($caption !== ''): ?>
                                <figcaption class="sabrina-legend"><?= $caption ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="carousel-btn prev" type="button" aria-label="Image précédente">‹</button>
            <button class="carousel-btn next" type="button" aria-label="Image suivante">›</button>

            <div class="carousel-dots" aria-hidden="true">
                <?php foreach (($gallery ?? []) as $i => $_): ?>
                    <button class="dot" data-index="<?= (int)$i ?>" type="button" aria-label="Aller à l’image <?= (int)$i + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script>
        (function() {
            const root = document.getElementById('sabrinaCarousel');
            if (!root) return;

            const track = root.querySelector('.carousel-track');
            const slides = Array.from(root.querySelectorAll('.carousel-slide'));
            const prev = root.querySelector('.prev');
            const next = root.querySelector('.next');
            const dots = Array.from(root.querySelectorAll('.dot'));

            let index = 0;

            function goTo(i) {
                index = Math.max(0, Math.min(i, slides.length - 1));
                track.style.transform = 'translateX(' + (-index * 100) + '%)';
                dots.forEach((d, k) => d.classList.toggle('active', k === index));
                prev.disabled = (index === 0);
                next.disabled = (index === slides.length - 1);
            }

            prev.addEventListener('click', () => goTo(index - 1));
            next.addEventListener('click', () => goTo(index + 1));
            dots.forEach(dot => dot.addEventListener('click', () => goTo(parseInt(dot.dataset.index, 10))));

            // Navigation clavier
            root.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') goTo(index + 1);
                if (e.key === 'ArrowLeft') goTo(index - 1);
            });

            // Init
            goTo(0);
        })();
    </script>



    </div>
</section>


<!-- Services -->
<section class="sabrina-services">
    <h2>Services</h2>
    <ul>
        <li>Coaching personnalisé (objectifs & suivi)</li>
        <li>Renforcement musculaire & mobilité</li>
        <li>Remise en forme & bien-être</li>
    </ul>
</section>

<!-- Call To Action -->
<section class="sabrina-signup">
    <h2>Prêt(e) à commencer ?</h2>
    <p>Crée ton compte ou connecte-toi pour réserver des créneaux.</p>
    <p>
        <a class="btn btn-primary signup-button" href="<?= BASE_URL ?>?action=inscription">Inscription</a>
        &nbsp;
        <a class="btn signup-button" href="<?= BASE_URL ?>?action=connexion">Connexion</a>
    </p>
</section>

</section>