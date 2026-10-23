---
titre: "Faille PHP2Shell : votre site internet est-il concerné ?"
extrait: "Fin octobre 2026, deux failles critiques dans PHP ont poussé des sites publics à se mettre en maintenance. Ce qui s'est passé, comment savoir si votre site est concerné et quoi faire."
categorie: Actualités
tags: [php, sécurité, site web, hébergement]
meta_titre: "Faille PHP2Shell : votre site est-il concerné ? Ce qu'il faut faire"
meta_description: "Deux failles critiques dans PHP (octobre 2026) permettent de prendre le contrôle d'un serveur. Comment vérifier votre site et quoi faire selon votre hébergement."
---

Le 21 octobre 2026 dans l'après-midi, plusieurs sites de préfectures ont cessé de répondre. Le ministère de l'Intérieur a expliqué procéder à une « maintenance préventive » de ses sites exposés à une vulnérabilité critique. En cause : deux failles découvertes dans **PHP**, le langage qui fait tourner une grande partie des sites internet, peut-être le vôtre.

Pas de panique : dans la plupart des cas, il n'y a presque rien à faire. Mais il faut savoir **où vérifier**. On fait le point, simplement.

## Ce qui s'est passé

Le 20 octobre, l'équipe chargée de la sécurité de PHP a publié une annonce inhabituelle : un correctif urgent allait sortir le lendemain pour deux failles jugées critiques. Des informations ayant fuité en ligne, la course contre la montre était lancée.

Le correctif est sorti le **21 octobre**. Les deux failles, surnommées **PHP2Shell** (références CVE-2026-109618 et CVE-2026-109619), sont notées **9,2 sur 10** en gravité. Elles permettent à un pirate d'**exécuter son propre code sur le serveur** à distance, sans mot de passe. Autrement dit, de prendre la main sur le site, de voler des données ou d'y cacher des logiciels malveillants.

L'ANSSI, l'agence nationale chargée de la cybersécurité, a recensé **plus de 600 000 serveurs PHP** accessibles depuis internet en France. Le CERT-FR a recommandé de mettre hors ligne, quand c'était possible, les serveurs qui ne pouvaient pas être corrigés tout de suite. D'où les pages de maintenance aperçues sur des sites publics.

Petite anecdote : le défaut à l'origine de la première faille avait été repéré dès 2024 lors d'un audit, sans qu'on sache alors l'exploiter. Cet été, plusieurs équipes de chercheurs, dont certaines aidées par des outils d'intelligence artificielle, ont trouvé comment s'en servir.

## Pourquoi c'est sérieux

La plupart des failles dont on parle touchent un logiciel précis : une extension WordPress, un thème, un module de boutique en ligne. Ici, c'est **le moteur PHP lui-même** qui est concerné. Peu importe que votre site soit bien entretenu : si la version de PHP installée sur le serveur n'est pas corrigée, il est exposé.

Des tentatives d'attaque ont été repérées dans les heures qui ont suivi la publication. Les spécialistes ne sont pas tous d'accord sur l'ampleur des attaques réussies, mais une chose est sûre : un serveur non corrigé est une cible.

## Votre site est-il concerné ?

Votre site utilise PHP si :

- il est fait avec **WordPress**, **PrestaShop**, **Joomla** ou **Drupal** ;
- il a été développé sur mesure avec **Symfony** ou **Laravel** ;
- votre hébergeur vous propose de choisir une « version PHP » dans votre espace client.

Ce qui compte ensuite, c'est **la version de PHP** installée sur le serveur. Les versions corrigées sont :

- **PHP 8.5.12** ou plus récent ;
- **PHP 8.4.27** ou plus récent ;
- **PHP 8.3.36** ou plus récent ;
- **PHP 8.2.35** ou plus récent.

Attention : les versions **8.1 et plus anciennes** (8.0, 7.4, 7.x, 5.x) ne sont plus suivies par l'équipe PHP et **ne recevront pas de correctif officiel**. Si votre site tourne encore sur l'une d'elles, c'est le moment de passer à une version récente.

> [!ASTUCE]
> Votre site est sous WordPress ? Allez dans **Outils → Santé du site**, onglet **Informations**, puis ouvrez la section **Serveur** : la version de PHP y est indiquée.

## Que faire selon votre situation

### Votre site est sur un hébergement mutualisé

C'est le cas de la grande majorité des sites de particuliers, d'artisans et de petites entreprises (o2switch, OVH, Ionos, Hostinger…). Ici, **c'est l'hébergeur qui installe le correctif** sur ses serveurs. Plusieurs l'ont fait dans les heures qui ont suivi ; o2switch, par exemple, a annoncé un déploiement automatique sur tous ses serveurs.

Ce qu'il vous reste à faire :

1. Consultez les annonces de votre hébergeur (site, newsletter ou réseaux sociaux) pour confirmer que le correctif est appliqué.
2. Dans votre espace client, vérifiez **la version de PHP choisie pour votre site**. Si c'est une version 8.1 ou plus ancienne, prévoyez de passer à une version récente.
3. Faites une **sauvegarde** avant tout changement de version : un vieux site ou une vieille extension peut mal réagir.

### Vous avez votre propre serveur (VPS ou serveur dédié)

Dans ce cas, la mise à jour est **à votre charge**. Installez la version corrigée de PHP via votre gestionnaire de paquets, puis **redémarrez PHP** (PHP-FPM ou Apache) pour que le correctif soit réellement pris en compte. Aucun réglage ne remplace la mise à jour : c'est la seule protection complète.

### Votre site est géré par un prestataire

Posez-lui simplement la question, par exemple :

> Mon site est-il protégé contre les failles PHP2Shell d'octobre 2026 ? Quelle version de PHP utilise-t-il ?

Un prestataire sérieux saura vous répondre rapidement.

## Les bons réflexes pour la suite

Cette alerte est un bon rappel de quelques règles simples :

- **Gardez une version de PHP maintenue.** Chaque version n'est suivie que quelques années : au-delà, plus aucune faille n'est corrigée.
- **Mettez à jour votre CMS et vos extensions** régulièrement, et supprimez celles que vous n'utilisez plus.
- **Ayez des sauvegardes récentes**, stockées ailleurs que sur le serveur du site.
- **Choisissez un hébergeur réactif** sur la sécurité : c'est lui qui vous protège en premier.

## En résumé

PHP2Shell est l'une des failles les plus sérieuses de ces dernières années, parce qu'elle touche le cœur de très nombreux sites. Si votre site est sur un hébergement mutualisé récent, il y a de bonnes chances qu'il soit déjà protégé : prenez quand même cinq minutes pour vérifier la version de PHP.
