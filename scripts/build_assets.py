# -*- coding: utf-8 -*-
"""Pipeline de preparação de assets para o site da JOTA Advocacia.
Lê os arquivos originais (com acentos/espaços) das pastas estampas/fotos/logo/
tipografia e arquivos/ na raiz do projeto, e gera versões otimizadas para web
em assets/img e assets/fonts, também na raiz.
"""
import os
import shutil
from PIL import Image, ImageOps

ROOT = r"C:\projetos\SiteJotaAdvocacia"
PUBLIC = ROOT
ARQ = os.path.join(ROOT, "arquivos")
IMG_OUT = os.path.join(PUBLIC, "assets", "img")
FONT_OUT = os.path.join(PUBLIC, "assets", "fonts")

os.makedirs(IMG_OUT, exist_ok=True)
os.makedirs(FONT_OUT, exist_ok=True)


def save_png(im, path, optimize=True):
    im.save(path, "PNG", optimize=optimize)
    print("PNG  ->", path, os.path.getsize(path) // 1024, "KB")


def save_jpg(im, path, quality=82):
    im = im.convert("RGB")
    im.save(path, "JPEG", quality=quality, optimize=True, progressive=True)
    print("JPG  ->", path, os.path.getsize(path) // 1024, "KB")


def save_webp(im, path, quality=80):
    im.save(path, "WEBP", quality=quality, method=6)
    print("WEBP ->", path, os.path.getsize(path) // 1024, "KB")


def resize_max_width(im, max_w):
    if im.width <= max_w:
        return im
    ratio = max_w / im.width
    return im.resize((max_w, int(im.height * ratio)), Image.LANCZOS)


def trim_alpha(im):
    """Remove margens transparentes de PNGs recortados."""
    im = im.convert("RGBA")
    bbox = im.getbbox()
    if bbox:
        im = im.crop(bbox)
    return im


def save_webp_alpha(im, path, quality=82):
    im = im.convert("RGBA")
    im.save(path, "WEBP", quality=quality, method=6)
    print("WEBP ->", path, os.path.getsize(path) // 1024, "KB")


def crop_cover(im, target_w, target_h, vertical_bias=1 / 3):
    """Recorta a imagem para preencher target_w x target_h (estilo object-fit: cover).
    vertical_bias controla onde fica a "janela" de corte verticalmente quando a
    imagem de origem é mais estreita/alta que o alvo (0 = topo, 1 = base)."""
    src_ratio = im.width / im.height
    tgt_ratio = target_w / target_h
    if src_ratio > tgt_ratio:
        new_w = int(im.height * tgt_ratio)
        left = (im.width - new_w) // 2
        im = im.crop((left, 0, left + new_w, im.height))
    else:
        new_h = int(im.width / tgt_ratio)
        top = int((im.height - new_h) * vertical_bias)
        top = max(0, min(top, im.height - new_h))
        im = im.crop((0, top, im.width, top + new_h))
    return im.resize((target_w, target_h), Image.LANCZOS)


# ---------------------------------------------------------------------------
# LOGOS
# ---------------------------------------------------------------------------
logo_dir = os.path.join(PUBLIC, "logo")

# Logo horizontal navy (para header em fundo claro)
im = Image.open(os.path.join(logo_dir, "Página 1.png"))
save_png(im, os.path.join(IMG_OUT, "logo-navy.png"))

# Versão compacta (símbolo + JOTA empilhado) navy
im = Image.open(os.path.join(logo_dir, "Página 2.png"))
save_png(im, os.path.join(IMG_OUT, "logo-navy-stacked.png"))

# Ícone isolado navy (marca d'água / referência)
im = Image.open(os.path.join(logo_dir, "Página 4.png"))
save_png(im, os.path.join(IMG_OUT, "logo-icon-navy.png"))

# Badge quadrado navy com ícone branco
im = Image.open(os.path.join(logo_dir, "Página 5.png"))
save_png(im, os.path.join(IMG_OUT, "logo-badge.png"))

# Favicon oficial da marca (arquivo dedicado em /logo)
favicon_src = os.path.join(logo_dir, "favicon.png")
if os.path.exists(favicon_src):
    favicon_im = Image.open(favicon_src).convert("RGBA")
    save_png(favicon_im, os.path.join(IMG_OUT, "favicon.png"))
    # Versão quadrada para apple-touch-icon (recorte central proporcional)
    w, h = favicon_im.size
    side = min(w, h)
    left = (w - side) // 2
    top = (h - side) // 2
    favicon_sq = favicon_im.crop((left, top, left + side, top + side))
    save_png(favicon_sq.resize((180, 180), Image.LANCZOS), os.path.join(IMG_OUT, "apple-touch-icon.png"))

# Logo horizontal branco (para header em fundo escuro / footer) - vem do kit de marca
branco_path = os.path.join(ARQ, "MARCA", "BRANCO", "Página 1.png")
if os.path.exists(branco_path):
    im = Image.open(branco_path)
    save_png(im, os.path.join(IMG_OUT, "logo-white.png"))

# Logo empilhado branco (splash screen)
branco_stacked = os.path.join(ARQ, "MARCA", "BRANCO", "Página 2.png")
if os.path.exists(branco_stacked):
    im = Image.open(branco_stacked)
    save_png(im, os.path.join(IMG_OUT, "logo-white-stacked.png"))

# Ícone branco isolado
branco_icon = os.path.join(ARQ, "MARCA", "BRANCO", "Página 4.png")
if os.path.exists(branco_icon):
    im = Image.open(branco_icon)
    save_png(im, os.path.join(IMG_OUT, "logo-icon-white.png"))

# ---------------------------------------------------------------------------
# ESTAMPAS (padrões decorativos) - opacas, exportar como JPG leve
# ---------------------------------------------------------------------------
estampas_dir = os.path.join(PUBLIC, "estampas")

estampa_map = {
    "Página 1.png": "pattern-navy-bold.jpg",     # padrão branco vibrante em navy
    "Página 2.png": "pattern-navy-subtle.jpg",   # padrão sutil navy sobre navy
    "Página 3.png": "pattern-navy-large.jpg",    # motivo grande navy/branco
    "Página 4.png": "pattern-light-large.jpg",   # motivo grande navy sobre claro
    "Página 5.png": "pattern-light-subtle.jpg",  # padrão sutil cinza sobre branco
    "Página 6.png": "pattern-navy-medium.jpg",   # padrão médio navy sobre navy
}
for src_name, out_name in estampa_map.items():
    im = Image.open(os.path.join(estampas_dir, src_name)).convert("RGB")
    im = resize_max_width(im, 1920)
    save_jpg(im, os.path.join(IMG_OUT, out_name), quality=84)

# ---------------------------------------------------------------------------
# FOTOS da Dra. Josi
# As fotos em /fotos já vêm recortadas pela cliente — aqui apenas redimensionamos
# mantendo a proporção original, sem crop adicional que distorcia o enquadramento.
# ---------------------------------------------------------------------------
fotos_dir = os.path.join(PUBLIC, "fotos")

fotos_originais = {
    1: "foto-josi (1).jpg",  # sentada, séria/confiante
    2: "foto-josi (2).jpg",  # sentada, sorrindo
    3: "foto-josi (3).jpg",  # em pé, corpo inteiro
    4: "foto-josi (4).jpg",  # em pé, 3/4, sorrindo
}

imgs = {k: Image.open(os.path.join(fotos_dir, v)) for k, v in fotos_originais.items()}
imgs = {k: ImageOps.exif_transpose(v) for k, v in imgs.items()}

# HERO - foto 2 (sorrindo)
hero = resize_max_width(imgs[2], 1000)
save_jpg(hero, os.path.join(IMG_OUT, "josi-hero.jpg"), quality=84)
save_webp(hero, os.path.join(IMG_OUT, "josi-hero.webp"), quality=82)
hero_sm = resize_max_width(hero, 600)
save_jpg(hero_sm, os.path.join(IMG_OUT, "josi-hero-sm.jpg"), quality=84)
save_webp(hero_sm, os.path.join(IMG_OUT, "josi-hero-sm.webp"), quality=82)

# SOBRE - foto 1 (confiante)
sobre = resize_max_width(imgs[1], 900)
save_jpg(sobre, os.path.join(IMG_OUT, "josi-sobre.jpg"), quality=84)
save_webp(sobre, os.path.join(IMG_OUT, "josi-sobre.webp"), quality=82)

# CTA final - foto 4 (3/4 sorrindo)
cta = resize_max_width(imgs[4], 900)
save_jpg(cta, os.path.join(IMG_OUT, "josi-cta.jpg"), quality=84)
save_webp(cta, os.path.join(IMG_OUT, "josi-cta.webp"), quality=82)

# Corpo inteiro - foto 3
full = resize_max_width(imgs[3], 800)
save_jpg(full, os.path.join(IMG_OUT, "josi-full.jpg"), quality=84)
save_webp(full, os.path.join(IMG_OUT, "josi-full.webp"), quality=82)

# OG IMAGE para compartilhamento em redes sociais (1200x630 — único recorte necessário)
og = crop_cover(imgs[2], 1200, 630, vertical_bias=0.35)
save_jpg(og, os.path.join(IMG_OUT, "og-image.jpg"), quality=85)

print("\nDimensões das fotos geradas (atualize width/height no index.html se mudar):")
for label, im in [("hero", hero), ("sobre", sobre), ("cta", cta), ("full", full)]:
    print(f"  {label}: {im.width} x {im.height}")

# ---------------------------------------------------------------------------
# FOTOS SEM FUNDO com gradiente na base (hero flutuante)
# Não aplicar trim_alpha — o fade inferior faz parte do recorte.
# ---------------------------------------------------------------------------
cutout_sources = {
    1: "foto-semfundo-josi (1).png",
    2: "foto-semfundo-josi (2).png",
}
cutouts = {}
for key, filename in cutout_sources.items():
    src = os.path.join(fotos_dir, filename)
    if not os.path.exists(src):
        print("AVISO: recorte não encontrado ->", src)
        continue
    im = ImageOps.exif_transpose(Image.open(src)).convert("RGBA")
    im = resize_max_width(im, 900)
    cutouts[key] = im
    base = f"josi-hero-cutout-{key}"
    save_png(im, os.path.join(IMG_OUT, f"{base}.png"))
    save_webp_alpha(im, os.path.join(IMG_OUT, f"{base}.webp"), quality=84)
    sm = resize_max_width(im, 560)
    save_png(sm, os.path.join(IMG_OUT, f"{base}-sm.png"))
    save_webp_alpha(sm, os.path.join(IMG_OUT, f"{base}-sm.webp"), quality=84)

if cutouts:
    print("\nDimensões dos recortes do hero:")
    for key, im in cutouts.items():
        print(f"  cutout-{key}: {im.width} x {im.height}")

# ---------------------------------------------------------------------------
# FONTES
# ---------------------------------------------------------------------------
tipografia_dir = os.path.join(PUBLIC, "tipografia")

font_map = {
    os.path.join("PRINCIPAL (MODIFICADA)", "ROLLGATES.TTF"): "Rollgates.ttf",
    os.path.join("SECUNDÁRIA", "ABSARASANSTF-LIGHTSC.OTF"): "AbsaraSansTF-LightSC.otf",
    os.path.join("AUXILIAR", "EIDETICMODERN REGULAR.OTF"): "EideticModern-Regular.otf",
    os.path.join("TEXTO", "LATO-LIGHT.TTF"): "Lato-Light.ttf",
    os.path.join("TEXTO", "LATO-REGULAR.TTF"): "Lato-Regular.ttf",
    os.path.join("TEXTO", "LATO-BOLD.TTF"): "Lato-Bold.ttf",
    os.path.join("TEXTO", "LATO-BLACK.TTF"): "Lato-Black.ttf",
    os.path.join("TEXTO", "LATO-ITALIC.TTF"): "Lato-Italic.ttf",
}
for src_rel, out_name in font_map.items():
    src = os.path.join(tipografia_dir, src_rel)
    dst = os.path.join(FONT_OUT, out_name)
    shutil.copyfile(src, dst)
    print("FONT ->", dst, os.path.getsize(dst) // 1024, "KB")

print("\nConcluído.")
