{
    "types": [
        {
            "code:": "adults",
            "name:": "Adults"
        },
        {
             "code": "concessions",
             "name": "Concessions"
        },
        {
             "code": "children",
             "name": "Children<br />(age 3-15 inclusive, under 3's travel for free)"
        },
        {
             "code": "dogs",
             "name": "Dogs"
         }
    ],
    "tickets": [
        {
            "code": "family_2_2",
            "name":"Family",
            "desc":"2 Adults + 2 Children",
            "composition": {
                "adults": 2,
                "children": 2,
                "concessions": 0,
                "dogs": 0
            },
            "depends": []
        },
        {
            "code": "family_1_1",
            "name":"Family",
            "desc":"1 Adult + 1 Child",
            "composition": {
                "adults": 1,
                "children": 1,
                "concessions": 0,
                "dogs": 0
            },
            "depends": []
        },
        {
            "code": "child_add",
            "name":"Additional Children",
            "desc":"Family tickets only",
            "composition": {
                "adults": 0,
                "children": 1,
                "concessions": 0,
                "dogs": 0
            },
            "depends": [
                "family_2_2", "family_1_1", "concession", "adult"
            ]
        },
        {
            "code": "adult",
            "name": "Adult",
            "desc":"",
            "composition": {
                "adults": 1,
                "children": 0,
                "concessions": 0,
                "dogs": 0
            },
            "depends": []
        },
        {
            "code": "concession",
            "name": "Concession",
            "composition": {
                "adults": 0,
                "children": 0,
                "concessions": 1,
                "dogs": 0
            },
            "desc":"Over 65 or disabled",
            "depends": []
        },
        {
            "code": "child",
            "name": "Unaccompanied Child",
            "composition": {
                "adults": 0,
                "children": 1,
                "concessions": 0,
                "dogs": 0
            },
            "desc":"age 3-15 inclusive",
            "depends": []
        },
        {
            "code": "dog",
            "name": "Dog",
            "composition": {
                "adults": 0,
                "children": 0,
                "concessions": 0,
                "dogs": 1
            },
            "desc":"",
            "depends": []
        }
    ],
    "prices": [
        {
            "station": 0,
            "destinations" : [
                {
                    "station": 2,
                    "return": [
                        {
                            "type": "family_2_2",
                            "price": 28
                        },
                        {
                            "type": "family_1_1",
                            "price": 14
                        },
                        {
                            "type": "child_add",
                            "price": 3
                        },
                        {
                            "type": "adult",
                            "price": 12
                        },
                        {
                            "type": "concession",
                            "price": 11
                        },
                        {
                            "type": "child",
                            "price": 6
                        },
                        {
                            "type": "dog",
                            "price": 1
                        }
                    ],
                    "single": [
                        {
                            "type": "child_add",
                            "price": 3
                        },
                        {
                            "type": "adult",
                            "price": 8
                        },
                        {
                            "type": "concession",
                            "price": 7.5
                        },
                        {
                            "type": "child",
                            "price": 4
                        },
                        {
                            "type": "dog",
                            "price": 1
                        }
                    ]
                }
            ]
        }
    ]

}
