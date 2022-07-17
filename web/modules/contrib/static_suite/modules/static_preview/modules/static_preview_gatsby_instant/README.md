# Instant preview for Gatsby
This module enables an instant preview system for sites built by
"Static Builder - Gatsby" module, which is a plugin for Static Build module.
It is "instant" because there is no need to run a build to visualize changes.

## INTRODUCTION ##
Gatsby renders pages in two ways:
* On build step: it renders React components to strings and saves its
contents to HTML files. This is also known as Server Side Rendering (SSR).
* On runtime: when a page is loaded in a browser, the previously generated HTML
file is rendered. After that, Gatsby loads a `page-data.json` file and React
"rehydrates" the page with the contents from that JSON file. Subsequents
clicks on links do not load a new HTML page; they fetch a a new JSON file
that is rendered using a React component. Which component should be  used is
also defined in that JSON file.

On the "runtime" mode, Gatsby needs two files per page to work properly:
* The HTML page that loads the React environment and rehydrates the page.
* The JSON file with the page's content, that will be used to rehydrate the
page.

These two files are only present as a result of running a build, but thanks to
 Drupal, we can dynamically generate both files so changes we made are visible
 immediately.

## PRELIMINARIES ##
This module needs your Gatsby site to be developed in a specific way to work
properly:
* Data exported by Static Export module must be passed to React components
as-is, without selecting a subset of data or doing any processing, i.e. all
content from JSON files must be passed from beginning to end. This can
be done in two ways:
    * Reading all JSON files from filesystem in `gatsby-node.js` and using page
    context to pass its contents to `createPage` function. This is the
    recommended way and the fastest one when dealing with thousand of JSON
    files.
    * Using GraphQL to get all content from source JSON files. This approach
    means adding an extra step to obtain exactly the same data already stored
    in JSON files.
* Pages with dynamic data (lists coming from a GraphQL query, etc) can not be
previewed because resolving that query means running a build. You can solve
this issue by exporting a JSON file with the contents of that query (Static
Export module and its event system enables doing it).
* Processes done by Node.js during build step should be avoided, since they
can not be replicated by this module.
* Your site should follow a structure where pages share a header, menu and
footer, and editing a content updates the "main" part of the page and not other
pages or blocks that may appear in multiples pages.

## REQUIREMENTS ##
To make this module work, we need to dynamically serve all files that Gastby
needs (HTML page, JSON File, etc). Follow these steps:

### Make exported data accessible (optional) ###
If some of the data exported by Static Export is made available on a public
URL (to be later fetched using XHR, maybe using the `static` folder from
Gatsby) you need to make that data manually accessible on a public URL. For
example, on Apache you might use an `Alias` directive like this one:

`Alias "/preview-data" "/var/www/my-site.com/static/data"`

Then, in your React components, you should check if the site is running in
preview mode and change XHR calls to point to `/preview-data`.

### Create a preview component ###
Create a preview component that will render all your site's content types. When
a site is running on "live" mode, each content type should be rendered by a
different component. But on "preview" mode, you should use a special component
that receives a prop (`pageContext`) and renders every content type by checking
 its bundle:

```
src/Preview.jsx
-------------------------------------------------------------------------------
import React from 'react';
import Article from 'Article';
import Homepage from 'Homepage';
import Page from 'Page';

const Preview = ({ pageContext }) => {
  // This switch depends on the structure of your data.
  switch (pageContext.node.data.content.bundle) {
    case 'article':
      return <Article pageContext={pageContext} />;
    case 'homepage':
      return <Homepage pageContext={pageContext} />;
    case 'page':
      return <Page pageContext={pageContext} />;
    default:
      return null;
  }
};

export default Preview;
```

### Create a preview page ###
Create a page that uses the above preview component. You should use
`createPage` inside `gatsby-node.js` and it must be called only when running
a "preview" build:

```
gatsby-node.js
-------------------------------------------------------------------------------

// ...

exports.createPages = ({ actions }) => {
  // Defining GATSBY_IS_PREVIEW in a .env file is a best practice.
  if (process.env.GATSBY_IS_PREVIEW) {
    createPage({
      path: '/preview',
      component: resolve(`./src/Preview.jsx`),
      context: {
        node: {
          data: {
            content: {
              url: { path: '/preview' }
              bundle: 'preview'
            }
          }
        }
      }
    });
  } else {
    // Create standard pages for "live" mode
    // ...
  }
};

// ...

```

In the above example, we are creating this page on a `/preview` path. It's
recommended to keep the component path and its route the same (`/preview`), as
this is the path you should enter in
`/admin/config/static/preview/gatsby/instant`

This module will intercept all requests to pages and will respond with a
tweaked version of the above page.

### Configure module to run conditional builds ###
If your site follows a structure where pages share a header, menu and footer
(from now on, let's call it the UI), and editing a content updates the "main"
part of the page and not other pages or blocks, you should configure which
files exported by Static Export module are used in the UI (configuration is
available at `/admin/config/static/build`).

Sample configuration for 'Regular Expressions to detect changed files that
trigger a "preview" build' field (tweak it to your needs):

```
config\/.*
locale\/.*
menu\/.*
```

This way, when a content is edited, the preview version of your site won't be
built. Running a build would only happen when one of the files used by the UI
are edited.

### Show build progress bar (optional) ###
When the UI has changed and a preview build is running, this module will add
 some stats about that build into the page that Drupal is serving. You can use
 that data to show an interactive progress bar to inform your editors of that
 process.

 This is a working example of a progress bar showing that info:


 ```
 ProgressBar.jsx
 ------------------------------------------------------------------------------

 import React from 'react';
 import PropTypes from 'prop-types';

 const progressBarStyles = {
   position: 'relative',
   height: 16,
   width: '100%'
 };

 export const ProgressBar = ({ percentage }) => {
   const fillerWidth = percentage > 100 ? 100 : percentage;
   const fillerStyles = {
     background: '#000',
     textAlign: 'right',
     color: '#fff',
     fontSize: 12,
     fontWeight: 'bold',
     width: `${fillerWidth}%`,
     height: '100%',
     transition: 'width .2s ease-in'
   };

   return (
     <div style={progressBarStyles}>
       <div style={fillerStyles}>{fillerWidth > 0 && `${fillerWidth}%`}</div>
     </div>
   );
 };

 ProgressBar.defaultProps = {
   percentage: 0
 };

 ProgressBar.propTypes = {
   percentage: PropTypes.number
 };
 ```

 ```
 DrupalStaticBuildStatus.jsx
 ------------------------------------------------------------------------------

 import React, { useState, useEffect } from 'react';
 import { ProgressBar } from './ProgressBar';

 const isDrupalSessionActive = () => {
   if (typeof document === 'object') {
     const rawCookies = `; ${document.cookie}`;
     // This cookie is set by Static Build module.
     return rawCookies.indexOf(' DRUPAL_AUTHENTICATED_USER=') >= 0;
   }
   return false;
 };

 const divStyles = {
   position: 'fixed',
   top: 0,
   width: '100%',
   minHeight: 44,
   zIndex: 999999,
   background: '#fc0'
 };

 const captionStyles = {
   textAlign: 'center',
   fontWeight: 'bold',
   fontFamily: 'Arial',
   padding: '0 10'
 };

 const buttonStyles = {
   display: 'inline-block',
   border: 'none',
   margin: '0 10',
   backgroundColor: '#000',
   color: '#fff',
   fontSize: 13,
   boxShadow: 'none',
   fontWeight: 'bold'
 };

 export const DrupalStaticBuildStatus = () => {
   // Don't render anything on SSR
   if (
     typeof window === 'undefined' ||
     !window.GATSBY_INSTANT_PREVIEW___RUNNING_BUILD_DATA ||
     // Tweak this value to your needs.
     window.location.hostname !== 'my-site.com' ||
     !isDrupalSessionActive()
   ) {
     return null;
   }

   const buildData = window.GATSBY_INSTANT_PREVIEW___RUNNING_BUILD_DATA;

   const [state, setState] = useState(() => {
     return {
       buildIsRunning: !!buildData.unique_id,
       buildIsAboutToFinish:
         buildData.unique_id && buildData.remaining_seconds < 2,
       percentage: buildData.percentage
     };
   });

   let timeoutId;
   let incrementPerSecond = 0;
   if (state.buildIsRunning) {
     incrementPerSecond = Math.round(
       (100 - buildData.percentage) / buildData.remaining_seconds
     );
     if (incrementPerSecond === 0) {
       incrementPerSecond = 1;
     }
   }

   const stopRefreshing = () => {
     clearTimeout(timeoutId);
     setState({
       buildIsRunning: false,
       buildIsAboutToFinish: false,
       percentage: 0
     });
   };

   useEffect(() => {
     if (state.buildIsRunning && !state.buildIsAboutToFinish) {
       timeoutId = setTimeout(() => {
         const percentage = state.percentage + incrementPerSecond;
         if (state.percentage < 100) {
           setState({
             buildIsRunning: state.buildIsRunning,
             buildIsAboutToFinish: state.buildIsAboutToFinish,
             percentage: percentage > 100 ? 100 : percentage
           });
         } else {
           stopRefreshing();
         }
       }, 1000);
     }
     return () => clearTimeout(timeoutId);
   });

   const message = state.buildIsAboutToFinish
     ? 'There is a preview build process about to finish (no ETA available).
        Please, reload this page in a few seconds to see last changes.'
     : 'There is a preview build process in progress. Please, keep in mind
        that some parts of the header, menu or footer may appear outdated
        until that process finishes.';

   return state.buildIsRunning ? (
     <div style={divStyles}>
       {!state.buildIsAboutToFinish && state.percentage && (
         <ProgressBar percentage={state.percentage} />
       )}
       <div style={captionStyles}>
         {message}
         {' '}
         <button
           type="button"
           onClick={stopRefreshing}
           onKeyPress={stopRefreshing}
           role="link"
           tabIndex={0}
           style={buttonStyles}
         >
           OK, close this box
         </button>
       </div>
     </div>
   ) : null;
 };

 ```

### Edit web server configuration ###
Instead of serving your site from the "live" folder, you should change your web
server configuration to point to the "preview" folder. Please, see
`static_builder_gatsby` module for an example for Apache web server (you should
replace `/live/` with `/preview/`).

## INSTALLATION ##
Run `composer require drupal/static_preview_gatsby_instant`

## CONFIGURATION ##
Available at `/admin/config/static/preview/gatsby/instant`
