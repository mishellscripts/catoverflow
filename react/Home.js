import React, { Component } from 'react';
import ReactDOM from 'react-dom';
import FileUploader from './FileUploader';
import VideoList from './VideoList';
import axios, { post } from 'axios';
import { status } from '../util/status';

// The Home component contains a FileUploader and VideoList
export default class Home extends Component {
    constructor(props) {
        super(props);
        this.state = {
            videos: [],
            error: null,
            status: status.WAITING,
        };
        this.updateVideos = this.updateVideos.bind(this);
        this.updateVideoList = this.updateVideoList.bind(this);
    }

    // Call updateVideoList() when the component is mounted
    componentDidMount() {
      this.updateVideoList();
    }

    // Refresh the video list
    updateVideoList() {
        this.setState({ status: status.FETCHING });
        this.updateVideos().then((response) => {
            console.log(response.data.data);
            this.setState({
                videos: response.data.data,
                status: status.SUCCESSFUL,
            });
        })
        .catch((error) => {
            console.log(error);
            this.setState({
                error: error.response.data,
                status: status.FAILURE,
            });
        });
    }

    // This make the AJAX call to fetch new video list
    updateVideos() {
      const url = 'http://localhost:8000/api/originalVideos';
      const formData = new FormData();
      formData.append('token', this.props.token);
      const config = {
          headers: {
              'content-type': 'multipart/form-data'
          }
      }
      return post(url, formData, config)
    }

    render() {
        return (
          <div className="container">
              <div className="row justify-content-center">
                  <FileUploader
                      token={this.props.token}
                      handleSuccess={this.updateVideoList}
                  />
                  <VideoList
                      videos={this.state.videos}
                      token={this.props.token}
                      updateVideoList={this.updateVideoList}
                      status={this.state.status}
                  />
              </div>
          </div>
        );
    }
}

// Allow props to be passed from outside
if (document.getElementById('Home')) {
    const element = document.getElementById('Home');
    const props = Object.assign({}, element.dataset);
    ReactDOM.render(<Home {...props} />, element);
}
