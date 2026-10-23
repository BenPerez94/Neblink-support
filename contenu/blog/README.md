# Publier un nouvel article

Tout se fait dans l'**admin en ligne** : pas besoin de Git, de SSH ni du site en local.
Ce dossier sert d'archive aux fichiers `.md` des articles.

## 1. Préparer le fichier

L'article est un fichier `.md` qui commence par un en-tête entre deux lignes `---` :

```markdown
---
titre: "Le titre de l'article"
slug: adresse-de-l-article        # l'adresse sera /blog/adresse-de-l-article
extrait: "Une à deux phrases affichées sur les cartes du blog et dans Google."
categorie: Sécurité               # Actualités, Dépannage, Sécurité ou Sites web
tags: [windows, sauvegarde]       # 3 à 5 maximum
couverture_alt: "Description de la photo en une phrase"
meta_titre: "Titre pour Google (60 caractères environ)"             # optionnel
meta_description: "Description pour Google (155 caractères environ)" # optionnel
---

Le texte en Markdown. Titres de section avec ## et ###.

> [!ASTUCE]
> Un encart astuce s'écrit comme ça.
```

Seul le `titre` est obligatoire. Une catégorie qui n'existe pas encore est créée automatiquement.

## 2. Importer dans l'admin

1. Aller sur `https://www.neblink.fr/admin/blog` puis **Importer**.
2. Choisir le fichier `.md` et, si on l'a, la **photo de couverture**
   (JPG, PNG ou WebP, 5 Mo maximum, idéalement 1200 × 630).
3. **Importer en brouillon** : l'article s'ouvre dans l'éditeur.

Si un article existe déjà à la même adresse, l'import est refusé : changer le `slug`
dans le fichier ou supprimer l'ancien article.

## 3. Relire, retoucher, publier

1. **Enregistrer et voir** pour relire l'article sur le site (invisible des visiteurs tant qu'il est en brouillon).
2. Les corrections se font **directement dans l'éditeur de l'admin** : après l'import,
   c'est l'admin qui fait référence, le fichier `.md` n'est plus utilisé.
3. Dans **Publication**, choisir **Publier** puis **Enregistrer** :
   - date vide : l'article est en ligne tout de suite ;
   - date future : il est programmé et apparaîtra seul à cette date.

L'article apparaît alors sur `/blog`, dans le sitemap et dans le flux RSS.

---

## Pour info : la commande d'import

La même importation existe en ligne de commande, utile en local pour tester un fichier :

```bash
php bin/console app:blog:import contenu/blog/fichier.md
```

Avec la commande, l'en-tête accepte aussi `couverture: images/photo.jpg` (chemin relatif au
fichier `.md`), et `--update` met à jour un article existant de même adresse.
