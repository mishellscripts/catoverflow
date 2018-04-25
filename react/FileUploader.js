import React, { Component } from 'react';
import ReactDOM from 'react-dom';
import axios, { post } from 'axios';
import { status } from '../util/status';

export default class FileUploader extends Component {
    constructor(props) {
      super(props);
      this.state = {
        video: null,
        status: status.WAITING,
        error: '',
      };
      this.handleSubmit = this.handleSubmit.bind(this);
      this.fileUpload = this.fileUpload.bind(this);
      this.fileChange = this.fileChange.bind(this);
    }

    handleSubmit(e) {
      e.preventDefault();
      this.setState({ status: status.FETCHING });
      if (this.state.video.size <= 2000000) {
          this.fileUpload(e.target.name.value, this.state.video).then((response) => {
              console.log(response.data);
              this.setState({ status: status.SUCCESSFUL });
              this.props.handleSuccess();
          })
          .catch( (error) => {
            // console.log(error);
            this.setState({ status: status.FAILURE, error: error.response.data });
          });
      } else {
          this.setState({ status: status.FAILURE, error: 'File size too large. Max file size is 2 MB.' });
      }
    }

    fileUpload(name, file) {
      const url = 'http://localhost:8000/api/uploadVideo';
      const formData = new FormData();
      formData.append('name', name);
      formData.append('file', file);
      formData.append('token', this.props.token);
      const config = {
          headers: {
              'content-type': 'multipart/form-data'
          }
      }
      return post(url, formData, config)
    }

    fileChange (e) {
      //console.log(e.target.files[0]);
      this.setState({
        video: e.target.files[0]
      });
    }

    render() {
        return (
          <div className="col-md-8 mb-5">
              <div
                className="alert alert-success mt-2"
                hidden={this.state.status !== status.SUCCESSFUL}
              >
                  <strong>Success!</strong> Video uploaded
              </div>
              <div
                className="alert alert-danger mt-2"
                hidden={this.state.status !== status.FAILURE}
              >
                  <strong>Error!</strong> <span dangerouslySetInnerHTML={ {__html: this.state.error} } />
              </div>
              <div className="card">
                  <div className="card-header">Upload Your Videos</div>
                  <div className="card-body">
                    <form onSubmit={this.handleSubmit}>
                      <div className="row">
                        <div className="form-group col-md-6">
                          <label htmlFor="name">Video name:</label>
                          <input
                            id="name"
                            className="form-control"
                            name="name"
                            type="text"
                            required
                          />
                        </div>
                        <div className="form-group col-md-6">
                          <label htmlFor="video">Upload video:</label>
                          <input
                            id="video"
                            className="form-control-file"
                            name="video"
                            type="file"
                            accept=".mp4"
                            onChange={this.fileChange}
                            required
                          />
                        </div>
                      </div>
                      <div className="row">
                        <div className="form-group col-md-12">
                          <button
                            type="submit"
                            value="Submit"
                            className="btn btn-secondary btn-lg btn-block"
                            disabled={this.state.status === status.FETCHING}>
                            {this.state.status === status.FETCHING ?
                                <i className="fa fa-spinner fa-spin"></i> :
                                'submit'
                            }
                          </button>
                        </div>
                      </div>
                    </form>
                  </div>
              </div>
          </div>
        );
    }
}

if (document.getElementById('FileUploader')) {
    const element = document.getElementById('FileUploader');
    const props = Object.assign({}, element.dataset);
    ReactDOM.render(<FileUploader {...props} />, element);
}
