

# Data flow

Newspack Network works on the top of the Data Events API present in the Newspack plugin. This plugin will listen to some of the Data Events and propagate them to all sites in the network.

When events happen in a site, they are pushed to the `Event Log` in the Hub. Each Node will pull new events from this `Event Log` every five minutes.

It all starts with the `Accepted_Actions` class. There we define which Data Events will be propagated via the Network plugin. When the Newspack plugin's Data Events API fire any of these events, it will be captured by this plugin and propagated.

Let's see how this work by following a couple of examples:

## Events triggered in a Node

For events that happen in one of the Nodes in the network, here's what happens.

First, the plugin will register a `Webhook` in the Newspack plugin, that will send all events delcared in `Accepted_Actions` as webhooks to the Hub. This is done in `Node\Webhook`.

Once the webhook is sent, the Hub receives and process it. This happens in `Hub\Webhook`.

When received, each event is represented by an `Inconming_Event` object. There is one specific class for each one of the `Accepted_Actions`, and they all extend `Abstract_Incoming_Event`.

Every event is persisted in the `Event_Log`. This is done in `Abstract_Incoming_Event::_in_hubprocess()` and uses `Hub\Stores\Event_Log`, which stores the information in a custom DB table.

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

For example, the `class-event-log` store has its respective `class-event-log-item` class. And `class-subscriptions` store has its respective `class-subscription-item` item class.

These items are used by Admin classes to display items in Admin Dashboards and will also be used by the classes that will serve this events to the Nodes when they pull events from the Hub.

## Database

Classes that creates the buckets where information used by Stores are stored. Creates database tables or register custom post types.

## Admin

Classes that handle user facing admin pages


