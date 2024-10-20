import React, { Component } from "react";
import { Center, HStack, Spinner, Button, Box, Text, FlatList } from "native-base";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SecureStore from 'expo-secure-store';
import { ListItem } from "react-native-elements";
import Constants from "expo-constants";
import Messages from "../components/Notifications";
import * as WebBrowser from 'expo-web-browser';
import { create, CancelToken } from 'apisauce';

// custom components and helper files
import { translate } from "../util/translations";
import { loadingSpinner } from "../components/loadingSpinner";
import { loadError } from "../components/loadError";
import { removeData, logoutUser } from "../util/logout";

export default class More extends Component {
	constructor() {
		super();
		this.state = {
            isLoading: true,
            hasError: false,
            error: null,
            defaultMenuItems: [
                {
                    key: '0',
                    title: 'Contact',
                    path: 'Contact',
                    external: false,
                },
                {
                    key: '1',
                    title: 'Privacy Policy',
                    path: 'https://bywatersolutions.com/lida-app-privacy-policy',
                    external: true,
                }
            ]
		 };
	}

	componentDidMount = async () => {
        this.setState({
            isLoading: false,
        });
	};

	renderNativeItem = (item) => {
        if(item.external) {
            return (
                <ListItem bottomDivider onPress={() => {this.openWebsite(item.path)} }>
                    <ListItem.Content>
                        <Text bold>{item.title}</Text>
                    </ListItem.Content>
                    <ListItem.Chevron />
                </ListItem>
            );
        } else {
            return (
                <ListItem bottomDivider onPress={() => {this.onPressMenuItem(item.path)} }>
                    <ListItem.Content>
                        <Text bold>{item.title}</Text>
                    </ListItem.Content>
                    <ListItem.Chevron />
                </ListItem>
            );
        }
	};

	onPressMenuItem = (item) => {
		this.props.navigation.navigate(item, { item });
	};

	onPressLogout = async () => {
	    await removeData();
	    this.props.navigation.navigate("Permissions");
	}

	openWebsite = async (url) => {
	    WebBrowser.openBrowserAsync(url);
	}


	render() {
		if (this.state.isLoading) {
			return ( loadingSpinner() );
		}

		if (this.state.hasError) {
            return ( loadError(this.state.error) );
		}

		return (
			<Box>
				<FlatList
					data={this.state.defaultMenuItems}
					renderItem={({ item }) => this.renderNativeItem(item)}
					keyExtractor={(item, index) => index.toString()}
				/>

                <Center mt={5}>
                    <Button onPress={() => {this.onPressLogout()} }>{translate('general.logout')}</Button>
                    <Text mt={10} fontSize="xs" bold>{translate('app.version')} <Text fontSize="xs" color="coolGray.600">{global.version}</Text></Text>
                </Center>
			</Box>
		);
	}
}