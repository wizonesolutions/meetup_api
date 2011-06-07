Last updated 7 June 2011 by Mike Accardo <accardo [at] cpan [dot] org>

=== Introduction to using the Meetup API ===

Here's an example of making a call to Comments.

  <?php
    $test_key = 'yourkeyhere'; //Replace with your key
    $muApi = new MeetupAPIComments($test_key);
    $muApi->setQuery( array('group_urlname' => 'some-group-urlname',) ); //Replace with a real group's URL name - it's what comes after the www.meetup.com/
    set_time_limit(0);
    $muApi->setPageSize(200);
    $response = $muApi->getResponse();
    krumo($response);
  ?>

=== Example of dealing with UTC time ===

Here's an example of converting the UTC time field. 

In some API methods, like open events, the response returns a time and offset. The time is UTC time in milliseconds since the epoch, and the offset is the local offset from UTC time in milliseconds. 

In the MeetupAPIBase class, the UTC time is automatically divided by 1000. So in the example, we only have to divide the offset by 1000 to complete the conversion.    

  <?php

    date_default_timezone_set('UTC');

    // Get the response after making a request as shown in the first example
    $response = $muApi->getResponse();
    foreach($response->{"results"} as $event)
    {  
        $time = $event->{'time'};
	$offset = $event->{'utc_offset'}/1000;
	$time = date('Y-m-d H:i:s',$time + $offset);
    }
  ?>

=== Using unimplemented methods ===

This is totally possible. The MeetupAPIBase class is pretty rich and does most of the work. So you can either:

1. Copy and change one of the existing classes - just change the class name and the method called in the constructor. If you do this, please contribute back the new class, as it would be nice to have them all implemented.
2. Instantiate MeetupAPIBase directly. This is fine to do. For example:
  $muApi = new MeetupAPIBase($test_key, 'comments');
The previous line does the same thing as instantiating the MeetupAPIComments class, at least at the time of writing.

=== Future plans ===
Probably the first thing will be to add more intelligent validation. Right now, it essentially does nothing since the Meetup API itself will return error information to the meta object if there is a problem. But it'd be nice if the API would prevent erroneous requests from being made and thus save some API requests.
