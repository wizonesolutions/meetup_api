Last updated 25 July 2010 by Kevin Kaland <help [at] wizonesolutions [dot] com>

=== Introduction to using the Meetup API ===

Here's an example of making a call to Comments. This file was funnier the first time I wrote it, but I lost it :(

  <?php
    $test_key = 'yourkeyhere'; //Replace with your key
    $muApi = new MeetupAPIComments($test_key);
    $muApi->setQuery( array('group_urlname' => 'some-group-urlname',) ); //Replace with a real group's URL name - it's what comes after the www.meetup.com/
    set_time_limit(0);
    $muApi->setPageSize(200);
    $response = $muApi->getResponse();
    krumo($response);
  ?>

=== Using unimplemented methods ===

This is totally possible. The MeetupAPIBase class is pretty rich and does most of the work. So you can either:

1. Copy and change one of the existing classes - just change the class name and the method called in the constructor. If you do this, please contribute back the new class, as it would be nice to have them all implemented.
2. Instantiate MeetupAPIBase directly. This is fine to do. For example:
  $muApi = new MeetupAPIBase($test_key, 'comments');
The previous line does the same thing as instantiating the MeetupAPIComments class, at least at the time of writing.

=== Future plans ===
Probably the first thing will be to add more intelligent validation. Right now, it essentially does nothing since the Meetup API itself will return error information to the meta object if there is a problem. But it'd be nice if the API would prevent erroneous requests from being made and thus save some API requests.
