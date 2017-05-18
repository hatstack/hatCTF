# Hatstack CTF Framework v2

Rewritten from scratch, with sweetalert as the only library. The visual design was kept for
the front end.

## How to use

### Setup

1. Clone the respository
1. Either remove `.git` or disallow access to the `.git` folder.
1. Run `database.mysql` in your database.
1. Move `config-example.php` to `config.php` and configure the database.
1. Register on the CTF.
1. Go to `/setup.php` (in your browser) to make your user an admin.

You are now ready to setup challenges in the admin panel. Challenges can be disabled (and are
disabled by default), so you can enable them when the CTF starts (or during the CTF).

### Running the CTF

The scoreboard does long polling only for admin users. If you display the scoreboard on a
screen, you might want to log in as admin to make updates instantaneous. However, you might
not want to leave it unattended since it's logged in as an admin user... see the TODO item
below.

To stop people from submitting flags, disable all challenges (there is a button for that in
the admin panel). One can only submit flags for enabled challenges.

## Technical design

The application was built with maintainability in mind: short code that's easy to understand
without having to learn a framework. For example the mixture of PHP and HTML in
`admin/index.php` looks a little ugly, but it's extremely easy to understand and modify,
making the contribution threshold (and the time it takes to do modifications) very low.

Error codes are random and are supposed to be unique throughout the application, making it
easy to `grep` for one.

### Front end

`index.html` is the static front page. It loads the static resources in the `res` (resources)
folder. The application is entirely reloadless: there is no action that requires reloading
the page, at least for players (admins have a separate admin panel). This means that after
an initial 75KB or so, only API calls have to be done to update the data on the page.

`res/js/app.js` is the main Javascript file. It depends on a few functions defined in
`res/js/generic.js` like `$()` and `GET()`. It builds the menu, loads the challenges,
auto-updates the leaderboard, etc. The naming convention is as follows: `updateSomething` is
the receiver for data from the API (which is typically JSON). It will process the data and
then typically call `renderSomething` to render the update.

`renderSomething` is also called when the page changes. There is a global `page` variable
containing the value of one of the `PAGE_*` constants. E.g. if the page is
`PAGE_LEADERBOARD` and you call `updateMenu` it will show a link to the challenges page and
no longer a link to the leaderboard page; and if you call `updateChallenges` while the page
is still `PAGE_LEADERBOARD`, it will make sure to hide the challenges.

The application will automatically update the leaderboard by polling. Only admins are allowed
to do long polling, to prevent the connection pool from getting full. When not on the
leaderboard page, the refresh rate is much lower (around 25 seconds) compared to the
leaderboard page (around 4 seconds). The timing is randomized to prevent the thundering herd
problem. If you are on the leaderboard page and logged in with an admin user, long polling
will be used. The back-end will query the database a few times per second to provide
instant-looking updates (try it!).

### Back end

From Javascript, the `api/` is called. It always calls index.php and uses GET and POST
parameters so no routing setup is necessary, making it independent of any web server.

The API serves the challenges, the leaders (for the leaderboard), flag submissions, etc. See
`api/interface.php` for the API interface. The interface calls functions from
`api/functions.php`. The functions are required to sanitize data themselves, i.e. the
interface will just pass GET or POST data to the function directly.

A PHP session is used for server-side state. Every visitor gets a session (this might be
optimized to only logged-in users, or even stateless JWT). The API and admin panel use these
sessions. The session contains the current score of the logged in user, among other things.

## To do

- Fix showing which challenges you already finished.

- Challenge upload system (for admins).

- Implement a front- and back end for "uploaders" (a special permission below admin).

- Perhaps use parameterized queries. I'm not sure it's necessary, but it might be better when
  more people contribute to the project to not have the risk of SQLi.

- I just realized it's smart to restrict long polling... but not to admin users, because
  that means the admin user will be logged in on the public display (because you want the
  scoreboard display to update instantly), which might be left unattended.

- Implement CSRF on the player's side (not just in the admin panel)

- Do a pen test.

