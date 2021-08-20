# Primary issues and areas to refactor (as of Aug 20, 2021):

## Efficiency/performance of API requests:

* ESPECIALLY find a way to pre-load human readable names of stops along a route. It's incredibly inefficient to query for each stop name when displaying schedule.
* Writing more targeted API requests, caching some data in memory, writing some to database, and choosing a definition of "up-to-the-minute" that prevents duplicating recent data will all be useful techniques. These design decisions should be based on needs of the current and future data model.
* In particular, discuss with project manager/client/stakeholders what form(s) the schedule display should take.
* Discuss if/when 'Predictions' should be supplemented by 'Schedules' when real-time data is unavailable, or if hypothetical schedules should never be mixed with actual data.
* Add API key to increase request limit.

## Unite CSS & Links:

* Replace table of links with current (non-deprecated) and framework-based implementation. My understanding is the l() method currently used is deprecated in Drupal 8&9, what's current best practice for adding links?
* Minimize hand-written code (especially minimize hand-written HTML!) by using a framework-based method of adding classes to elements. It seems likely that a Drupal-based design can better unite the functionality with the style, compared to furthering these code-based implementations.
* OR - Hard code the list of routes! As opposed to route predictions that have ever-changing traffic conditions, real-time conditions have limited effects on the list of routes. Adding classes to elements on-the-fly, based on an API request, is what makes this more of a design challenge, and our needs may not require us to focus efforts here.
* Discuss sort-order with stakeholders.

## Separation of concerns:

* Separate HTTP Client into its own service controller.
* Move http request options from controller to services definition files.
* Replace hand-written render arrays in controller class. Choose a model for templating data display (Twig files, Entity objects, display block Plugins...). 
* Should this module constitute a full view/display, or is this a widget to be incorporated into a larger UI? Does the answer to that effect responsive design decisions?
## Error handling:
* In addition to question of supplementing with 'Schedules' when real-time 'Predictions' of routes are unavailable, what are other error handling considerations?
* When an API request fails, do we want to fall back on other data, or give the user an error message stating the real-time content is unavailable, or some combination of the two?
