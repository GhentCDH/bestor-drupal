nvm use 19

Jakarta
===========
Jakarta is a light Sass based Drupal starter theme.

First time using JAKARTA
==========================
Install globally once.

- Install NodeJS globally

install and download node js: http://nodejs.org/

Install dependencies
====================
Install needed dependencies for every project.

- Go to root of jakarta and install dependencies
1. nvm use (version in .nvmrc)
2. npm i

Tasks for CSS and JS
===========
- npm run watch (for dev)
- npm run build (for production, can only be used on projects with deploy to hosting)

Folder structure (Based on SMACSS, with a GBL twist)
===========
- Base:

Base rules are the defaults. They are almost exclusively single element selectors but it could include attribute selectors, pseudo-class selectors, child selectors or sibling selectors. Essentially, a base style says that wherever this element is on the page.

- Layout:

Divide the page into sections. Layouts hold one or more modules together. We also use layout for the items that appear on every page (e.g. header, footer, navigation,...).

- Components:

Are the reusable, modular parts of our design. They are the nodes, paragraphs, blocks,...

- Helpers:

Mixins, extendables,..
