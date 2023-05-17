




## Admin

Classes that handle user facing admin pages

## Database

Classes that creates the buckets where information used by Stores are stored. Creates database tables or register custom post types

## Stores

Classes that handle the database interaction. Stores and fetch items.

Each store has a `-item` respective class that will be used to represent the item they store. When you fetch items from a store, the respective item object will be returned.

These items are used by Admin classes to display items in Admin Dashboards and will also be used by the classes that will serve this events to the Nodes when they pull events from the Hub.

## Incoming events

Used by the Webhook handler.

Each accepted webhook action must have a respective Incoming_Event child class.

Every Income event will be stored in the Event_Log store, where we have the complete history of the events across the network. Nodes will pull data from this Log in order to be up to date with what's happening and be able to update their local databases. For example, they can update users metadata to reflect what they have done in other Nodes for segmentation purposes.

Each Income_Event type also declares a `post_process` method, that does more with the incoming data. For example when a user registers to a site, the `Reader_Registered` Incoming Event will also create the user in the Hub, and the Woocommerce Subscriptions and Orders events will create the posts in the Hub that will feed the Centralized Woo Dashboards.