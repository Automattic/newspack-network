

# Data flow

Newspack Network works on the top of the Data Events API present in the Newspack plugin. This plugin will listen to some of the Data Events and propagate them to all sites in the network.

When events happen in a site, they are pushed to the `Event Log` in the Hub. Each Node will pull new events from this `Event Log` every five minutes.

It all starts with the `Accepted_Actions` class. There we define which Data Events will be propagated via the Network plugin. When the Newspack plugin's Data Events API fire any of these events, it will be captured by this plugin and propagated.

Let's see how this work by following a couple of examples:

## Events triggered in a Node

For events that happen in one of the Nodes in the network, here's what happens.

First, the plugin will register a `Webhook` in the Newspack plugin, that will send all events delcared in `Accepted_Actions` as webhooks to the Hub. This is done in `Node\Webhook`.

Once the webhook is sent, the Hub receives and process it. This happens in `Hub\Webhook`.

When received, each event is represented by an `Inconming_Event` object. There is one specific class for each one of the `Accepted_Actions`, and they all extend `Incoming_Event`.

Every event is persisted in the `Event_Log`. This is done in `Incoming_Event::process_in_hub()` and uses `Hub\Stores\Event_Log`, which stores the information in a custom DB table.

After the event is persisted in the `Event_Log` it may fire additional post processing routines, depending on the event. These routines will be defined in a method called `post_process_in_hub()` present in each `Inconming_Event` class.

For example, the `reader_registered` event will create or update a WP User in the site, and the Woocommerce events will update the Woo items used to build the central Dashboards.

## Events triggered in the Hub

The Hub can also be an active site in the network. So events that happen on the Hub should also be propagated as if it was just another node.

But when an event is fired in the Hub, there's no need to send a webhook request to itself. So we simply listen to events fired by the Data Events API and trigger the process to persist it into the `Event_Log`. This is done by `Hub\Event_Listeners` and it basically does the same thing `Hub\Webhook` does, but it is listening to local events instead of receiving webhook requests from a Node.

## Nodes pulling events from the hub

Once events are persisted in the `Event_Log`, Nodes will be able to pull them and update their local databases.

They do this by making requests to the Hub every five minutes. In each request, they send along what is the ID of the last item they processed last time they pulled data from the Hub.

This pulling is done in `Node\Pulling`. In the Hub, the API endpoint that handles the pull requests coming from the nodes is registered in `Hub\Pull_Endpoint`.

Note that nodes are not necessarily interested in every event. So you will see that the `Accepted_Actions` class has a `ACTIONS_THAT_NODES_PULL` property that defines what action Nodes will ask for.

When they pull events, they get an array of events. Each event has a an action name, that maps to a `Incoming_Event`, just as they do when the Hub receives a webhook request from the Node.

The Node instantiates the corresponding `Incoming_Event` for each action and then calls the `process_in_node` method of the event object.

## Stores

Stores are simple abstraction layers used by the Hub to persist data on the database. They are used to store and read data.

For example, `Event_log` items are stored in a custom table, while Woo orders and subscriptions that are used to build the centralized dashboards are stored as custom post types. But it's all done via stores.

Each store has a `-item` respective class that will be used to represent the item they store. When you fetch items from a store, the respective item object will be returned.

For example, `class-subscriptions` store has its respective `class-subscription-item` item class.

`Event_Log` items are defined in the `event-log-items` folder as there is one class for each different action. The only thing they do is define a different `get_summary` method that will define what will be displayed in the Event Log admin table. If there is not a specific class for a given action, it will use the `Generic` class.

These items are used by Admin classes to display items in Admin Dashboards and will also be used by the classes that will serve this events to the Nodes when they pull events from the Hub.

## Database

Classes that creates the buckets where information used by Stores are stored. Creates database tables or register custom post types.

## Admin

Classes that handle user facing admin pages

## Adding support to a new action

These are the steps to add support to a new action:

1. Add it to `ACCEPTED_ACTIONS`

Edit `class-accepted-actions.php` and add a new item to the array. The key is the action name as defined in the Newspack Data API, and the value will be the class name used in this plugin. For example, this will define the class name of the `Incoming_Event` child class.

If all you need is to have this event persisted in the Hub. That's it.

If you also want this event to be pulled by all Nodes in the network, also add the action to the `ACTIONS_THAT_NODES_PULL` constant.

2. Create an `Incoming_Event` child class

In the `incoming-events` folder, create a new file named after the class name you informed in `ACCEPTED_ACTIONS`.

At first, this class doesn't need to have anything on it.

At this point, the event will be propagated and will be stored in the `Event Log`.

But it's very unlikely that won't do anything else with an event, so let's add other methods.

3. Implement `post_process_in_hub` and `process_in_node` methods

Depending on what you want to do with the event, and where, implement these 2 methods to perform some actions when this event is detected both in the hub and in the nodes. You can create a user, a post, add user or post meta, etc.

4. Optional. Create a `event-log-item` specific class

If you want to customize how this new event looks in the `Event Log`, go to `hub/stores/event-log-items` and create a new class named after the class you informed in `ACCEPTED_ACTIONS`. Implement the `get_summary` method to display the information the way you need.

## WP CLI

Available CLI commands are (add `--help` flag to learn more about each command):

### `wp np-network process`
* Will process `pending` `np_webhook_request`s and delete after processing.
* `--dry-run` enabled. Will run through process without deleting.
* `--yes` enabled. Will bypass confirmations.

## Troubleshooting

Here's how to debug and follow each event while they travel around.

First, make sure to add the `NEWSPACK_NETWORK_DEBUG` constant as `true` to every site wp-config file.

All log messages will include the process id (pid) as the first part of the message in between brackets. This is helpful to identify things happening in different requests. When debugging multiple parallel async actions, sometimes they get mixed up in the log.

### When an event is fired in a Node

Newspack Network will listen to the Newspack Data Events API.

When an event dispatched in a Node, it will create a new webhook request. See [Data Events Webhooks](https://github.com/Automattic/newspack-plugin/blob/trunk/includes/data-events/class-webhooks.php) for details on how it works.

In short, a webhook is a Custom Post type post scheduled to be published in the future. Once it's published, the request is sent. If it fails, it schedules itself again for the future, incresing the wait time in a geometric progression.

You can see the scheduled webhook requests in Newspack Network > Node Settings under the "Events queue" section.

* If you want to manually and immeditally send a webhook request, you can do so using `Newspack\Data_Events\Webhooks::process_request( $request_id )`

When the request is sent, Webhooks will output a message starting with `[NEWSPACK-WEBHOOKS] Sending request` in the logs.

When the request reaches the hub, you will see it on the Logs starting with a `Webhook received` message.

### When the Node pulls events from the Hub

At any point, it's a good idea to check the value for the `newspack_node_last_processed_action` option. It holds the ID of the last event received in the last pull.

Pulls are scheduled in CRON for every 5 minutes. If you want to trigger a pull now, you can do so by calling `Newspack_Network\Node\Pulling::pull()`

In the Node's log you will see detailed information about the pull attempt, starting with a `Pulling data` message.

In the Hub's log, you will also see detailed information about the pull request, starting with a `Pull request received` message.
